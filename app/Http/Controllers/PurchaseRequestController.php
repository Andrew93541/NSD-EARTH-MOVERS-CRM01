<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = PurchaseRequest::with(['project', 'site', 'warehouse', 'requester', 'approver', 'items.material'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('project_id') && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('site_id') && $request->site_id !== 'all') {
            $query->where('site_id', $request->site_id);
        }

        $requests = $query->paginate(12)->withQueryString();

        $stats = [
            'total_requests' => PurchaseRequest::count(),
            'pending_approval' => PurchaseRequest::where('status', 'pending')->count(),
            'approved' => PurchaseRequest::where('status', 'approved')->count(),
            'converted' => PurchaseRequest::whereIn('status', ['partially_converted', 'po_created'])->count(),
        ];

        $projects = Project::orderBy('name')->get();
        $sites = Site::orderBy('name')->get();

        return view('purchase-requests.index', compact('requests', 'stats', 'projects', 'sites'));
    }

    public function create(): View
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;

        $projects = Project::whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();

        $sitesQuery = Site::with('project')->where('status', 'active');
        if ($roleSlug === 'site_supervisor' && Site::where('supervisor_id', $user->id)->exists()) {
            $sitesQuery->where('supervisor_id', $user->id);
        }
        $sites = $sitesQuery->orderBy('name')->get();

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $materials = Material::with('category')->where('is_active', true)->orderBy('name')->get();

        $generatedPrNumber = 'PR-' . date('Y') . '-' . strtoupper(Str::random(5));

        return view('purchase-requests.create', compact('projects', 'sites', 'warehouses', 'materials', 'generatedPrNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'site_id' => 'required|exists:sites,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'required_by_date' => 'required|date|after_or_equal:today',
            'priority' => 'required|in:normal,urgent,critical',
            'reason' => 'required|string|min:5',
            'action' => 'required|in:draft,submit',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity_requested' => 'required|numeric|min:0.001',
            'items.*.specifications' => 'nullable|string|max:255',
            'items.*.estimated_rate' => 'nullable|numeric|min:0',
        ]);

        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;

        // Validate site belongs to project
        $site = Site::where('id', $validated['site_id'])
            ->where('project_id', $validated['project_id'])
            ->first();

        if (!$site) {
            return back()->withInput()->withErrors([
                'site_id' => 'The selected site does not belong to the selected project.',
            ]);
        }

        // Validate site supervisor authorization
        if ($roleSlug === 'site_supervisor' && $site->supervisor_id && $site->supervisor_id !== $user->id) {
            abort(403, 'You are not assigned to supervise this site.');
        }

        $purchaseRequest = DB::transaction(function () use ($validated, $user) {
            $isSubmitted = ($validated['action'] === 'submit');

            $pr = PurchaseRequest::create([
                'company_id' => $user->currentCompany()?->id ?? session('current_company_id'),
                'pr_number' => 'PR-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'project_id' => $validated['project_id'],
                'site_id' => $validated['site_id'],
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'request_date' => Carbon::today(),
                'submitted_at' => $isSubmitted ? Carbon::now() : null,
                'required_by_date' => $validated['required_by_date'],
                'status' => $isSubmitted ? 'pending' : 'draft',
                'priority' => $validated['priority'],
                'reason' => $validated['reason'],
                'requested_by' => $user->id,
            ]);

            foreach ($validated['items'] as $itemData) {
                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'material_id' => $itemData['material_id'],
                    'specifications' => $itemData['specifications'] ?? null,
                    'quantity_requested' => $itemData['quantity_requested'],
                    'quantity_ordered' => 0.000,
                    'estimated_rate' => $itemData['estimated_rate'] ?? 0.00,
                ]);
            }

            // Notification & Audit
            AuditLog::create([
                'company_id' => $pr->company_id,
                'user_id' => Auth::id(),
                'action' => $isSubmitted ? 'purchase_request_submitted' : 'purchase_request_drafted',
                'auditable_type' => PurchaseRequest::class,
                'auditable_id' => $pr->id,
                'new_values' => [
                    'pr_number' => $pr->pr_number,
                    'status' => $pr->status,
                    'items_count' => count($validated['items']),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if ($isSubmitted) {
                // Notify Project Manager
                $projectManagerId = $pr->project->manager_id;
                AppNotification::create([
                    'company_id' => $pr->company_id,
                    'user_id' => $projectManagerId,
                    'title' => 'New Material Purchase Request',
                    'message' => "Request {$pr->pr_number} submitted for site {$pr->site->name} ({$pr->priority} priority).",
                    'type' => ($pr->priority === 'critical' ? 'danger' : 'info'),
                    'action_url' => route('purchase-requests.show', $pr),
                    'is_read' => false,
                ]);
            }

            return $pr;
        });

        $msg = ($validated['action'] === 'submit')
            ? "Material Request {$purchaseRequest->pr_number} submitted for Project Manager approval."
            : "Material Request {$purchaseRequest->pr_number} saved as draft.";

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', $msg);
    }

    public function show(PurchaseRequest $request): View
    {
        $request->load([
            'project.manager',
            'site.supervisor',
            'warehouse',
            'requester',
            'approver',
            'items.material.category',
            'purchaseOrders.supplier',
            'purchaseOrders.creator',
        ]);

        // Calculate available stock context for each material in site warehouse or central yard
        $stockContext = [];
        $targetWarehouseId = $request->warehouse_id;

        foreach ($request->items as $item) {
            $query = StockLedgerEntry::where('material_id', $item->material_id);
            if ($targetWarehouseId) {
                $query->where('warehouse_id', $targetWarehouseId);
            }

            $in = (clone $query)->where('direction', 'in')->sum('quantity');
            $out = (clone $query)->where('direction', 'out')->sum('quantity');

            $stockContext[$item->material_id] = max(0.0, (float) ($in - $out));
        }

        return view('purchase-requests.show', compact('request', 'stockContext'));
    }

    public function edit(PurchaseRequest $request): View|RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAdminOrOwner = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);

        if ($request->requested_by !== $user->id && !$isAdminOrOwner) {
            abort(403, 'You are not authorized to edit this purchase request.');
        }

        if (!in_array($request->status, ['draft', 'rejected'])) {
            return redirect()->route('purchase-requests.show', $request)
                ->withErrors(['status' => 'Submitted or processed requests cannot be edited directly.']);
        }

        $request->load(['items.material']);
        $projects = Project::whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();
        $sites = Site::with('project')->where('status', 'active')->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $materials = Material::with('category')->where('is_active', true)->orderBy('name')->get();

        $existingItems = $request->items->map(fn($it) => [
            'material_id' => (string) $it->material_id,
            'specifications' => $it->specifications ?? '',
            'quantity_requested' => (float) $it->quantity_requested,
            'estimated_rate' => (float) $it->estimated_rate,
            'uom' => $it->material->unit_of_measure ?? '',
        ])->values()->all();

        return view('purchase-requests.edit', compact('request', 'projects', 'sites', 'warehouses', 'materials', 'existingItems'));
    }

    public function update(Request $httpRequest, PurchaseRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAdminOrOwner = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);

        if ($request->requested_by !== $user->id && !$isAdminOrOwner) {
            abort(403, 'You are not authorized to update this purchase request.');
        }

        if (!in_array($request->status, ['draft', 'rejected'])) {
            return redirect()->route('purchase-requests.show', $request)
                ->withErrors(['status' => 'Only draft or rejected requests can be updated.']);
        }

        $validated = $httpRequest->validate([
            'project_id' => 'required|exists:projects,id',
            'site_id' => 'required|exists:sites,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'required_by_date' => 'required|date|after_or_equal:today',
            'priority' => 'required|in:normal,urgent,critical',
            'reason' => 'required|string|min:5',
            'action' => 'required|in:draft,submit',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity_requested' => 'required|numeric|min:0.001',
            'items.*.specifications' => 'nullable|string|max:255',
            'items.*.estimated_rate' => 'nullable|numeric|min:0',
        ]);

        $site = Site::where('id', $validated['site_id'])
            ->where('project_id', $validated['project_id'])
            ->first();

        if (!$site) {
            return back()->withInput()->withErrors([
                'site_id' => 'The selected site does not belong to the selected project.',
            ]);
        }

        if ($roleSlug === 'site_supervisor' && $site->supervisor_id && $site->supervisor_id !== $user->id) {
            abort(403, 'You are not assigned to supervise this site.');
        }

        DB::transaction(function () use ($validated, $request, $user) {
            $isSubmitted = ($validated['action'] === 'submit');

            $request->update([
                'project_id' => $validated['project_id'],
                'site_id' => $validated['site_id'],
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'required_by_date' => $validated['required_by_date'],
                'priority' => $validated['priority'],
                'reason' => $validated['reason'],
                'rejection_reason' => $isSubmitted ? null : $request->rejection_reason,
                'status' => $isSubmitted ? 'pending' : 'draft',
                'submitted_at' => $isSubmitted ? Carbon::now() : $request->submitted_at,
            ]);

            // Replace items
            $request->items()->delete();
            foreach ($validated['items'] as $itemData) {
                PurchaseRequestItem::create([
                    'purchase_request_id' => $request->id,
                    'material_id' => $itemData['material_id'],
                    'specifications' => $itemData['specifications'] ?? null,
                    'quantity_requested' => $itemData['quantity_requested'],
                    'quantity_ordered' => 0.000,
                    'estimated_rate' => $itemData['estimated_rate'] ?? 0.00,
                ]);
            }

            AuditLog::create([
                'company_id' => $request->company_id,
                'user_id' => $user->id,
                'action' => $isSubmitted ? 'purchase_request_resubmitted' : 'purchase_request_updated',
                'auditable_type' => PurchaseRequest::class,
                'auditable_id' => $request->id,
                'new_values' => ['status' => $request->status],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if ($isSubmitted) {
                AppNotification::create([
                    'company_id' => $request->company_id,
                    'user_id' => $request->project->manager_id,
                    'title' => 'Purchase Request Resubmitted',
                    'message' => "Request {$request->pr_number} has been resubmitted for approval.",
                    'type' => 'info',
                    'action_url' => route('purchase-requests.show', $request),
                    'is_read' => false,
                ]);
            }
        });

        $msg = ($validated['action'] === 'submit')
            ? "Material Request {$request->pr_number} resubmitted for approval."
            : "Material Request {$request->pr_number} updated successfully.";

        return redirect()->route('purchase-requests.show', $request)->with('success', $msg);
    }

    public function submit(PurchaseRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAdminOrOwner = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);

        if ($request->requested_by !== $user->id && !$isAdminOrOwner) {
            abort(403, 'You are not authorized to submit this purchase request.');
        }

        if ($request->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft requests can be submitted.']);
        }

        $request->update([
            'status' => 'pending',
            'submitted_at' => Carbon::now(),
        ]);

        AuditLog::create([
            'company_id' => $request->company_id,
            'user_id' => $user->id,
            'action' => 'purchase_request_submitted',
            'auditable_type' => PurchaseRequest::class,
            'auditable_id' => $request->id,
            'new_values' => ['status' => 'pending'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Notify Project Manager
        AppNotification::create([
            'company_id' => $request->company_id,
            'user_id' => $request->project->manager_id,
            'title' => 'Purchase Request Submitted',
            'message' => "Request {$request->pr_number} is pending your approval.",
            'type' => 'info',
            'action_url' => route('purchase-requests.show', $request),
            'is_read' => false,
        ]);

        return redirect()->route('purchase-requests.show', $request)
            ->with('success', "Request {$request->pr_number} submitted to Project Manager for approval.");
    }

    public function approve(Request $httpRequest, PurchaseRequest $request): RedirectResponse
    {
        if ($request->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending requests can be approved.']);
        }

        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAuthorizedApprover = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin'])
            || ($roleSlug === 'project_manager' && ($request->project->manager_id === null || $request->project->manager_id === $user->id))
            || ($request->project->manager_id === $user->id);

        if (!$isAuthorizedApprover) {
            abort(403, 'You do not have authorization to approve this purchase request.');
        }

        // Segregation of duties: prevent self-approval unless company owner/admin
        $isOwnerOrAdmin = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);
        if ($request->requested_by === $user->id && !$isOwnerOrAdmin) {
            return back()->withErrors([
                'status' => 'Segregation of duties: You cannot approve your own purchase request.',
            ]);
        }

        $request->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ]);

        AuditLog::create([
            'company_id' => $request->company_id,
            'user_id' => $user->id,
            'action' => 'purchase_request_approved',
            'auditable_type' => PurchaseRequest::class,
            'auditable_id' => $request->id,
            'new_values' => ['status' => 'approved', 'approved_by' => $user->id],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Notify Requester
        AppNotification::create([
            'company_id' => $request->company_id,
            'user_id' => $request->requested_by,
            'title' => 'Material Request Approved',
            'message' => "Your request {$request->pr_number} was approved and queued for procurement PO creation.",
            'type' => 'success',
            'action_url' => route('purchase-requests.show', $request),
            'is_read' => false,
        ]);

        return redirect()->route('purchase-requests.show', $request)
            ->with('success', "Purchase Request {$request->pr_number} approved successfully.");
    }

    public function reject(Request $httpRequest, PurchaseRequest $request): RedirectResponse
    {
        if ($request->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending requests can be rejected.']);
        }

        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAuthorizedApprover = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin'])
            || ($roleSlug === 'project_manager' && ($request->project->manager_id === null || $request->project->manager_id === $user->id))
            || ($request->project->manager_id === $user->id);

        if (!$isAuthorizedApprover) {
            abort(403, 'You do not have authorization to reject this purchase request.');
        }

        $validated = $httpRequest->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);

        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ]);

        AuditLog::create([
            'company_id' => $request->company_id,
            'user_id' => $user->id,
            'action' => 'purchase_request_rejected',
            'auditable_type' => PurchaseRequest::class,
            'auditable_id' => $request->id,
            'new_values' => [
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Notify Requester
        AppNotification::create([
            'company_id' => $request->company_id,
            'user_id' => $request->requested_by,
            'title' => 'Material Request Rejected',
            'message' => "Your request {$request->pr_number} was rejected. Reason: {$validated['rejection_reason']}",
            'type' => 'danger',
            'action_url' => route('purchase-requests.show', $request),
            'is_read' => false,
        ]);

        return redirect()->route('purchase-requests.show', $request)
            ->with('warning', "Purchase Request {$request->pr_number} has been rejected.");
    }

    public function cancel(PurchaseRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAdminOrOwner = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);

        if ($request->requested_by !== $user->id && !$isAdminOrOwner) {
            abort(403, 'You are not authorized to cancel this purchase request.');
        }

        if (!in_array($request->status, ['draft', 'pending'])) {
            return back()->withErrors(['status' => 'Approved or processed requests cannot be cancelled.']);
        }

        $request->update(['status' => 'cancelled']);

        return redirect()->route('purchase-requests.index')
            ->with('info', "Purchase Request {$request->pr_number} was cancelled.");
    }

    public function createPOConversion(PurchaseRequest $request): View|RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isProcurement = $user->hasPermission('*') || $user->hasPermission('procurement.*') || in_array($roleSlug, ['owner', 'admin', 'procurement_officer', 'procurement']);

        if (!$isProcurement) {
            abort(403, 'You do not have permission to convert purchase requests into purchase orders.');
        }

        if (!in_array($request->status, ['approved', 'partially_converted'])) {
            return redirect()->route('purchase-requests.show', $request)
                ->withErrors(['status' => 'Only approved purchase requests can be converted into purchase orders.']);
        }

        if ($request->remainingItemsCount() === 0) {
            return redirect()->route('purchase-requests.show', $request)
                ->withErrors(['status' => 'All items in this request have already been fully converted into purchase orders.']);
        }

        $request->load(['items.material', 'project', 'site', 'warehouse']);
        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        $convertibleItems = $request->items
            ->filter(fn($it) => $it->remainingQuantity() > 0.001)
            ->values()
            ->map(fn($it) => [
                'pr_item_id' => $it->id,
                'material_name' => $it->material->name,
                'specifications' => $it->specifications ?? '',
                'uom' => $it->material->unit_of_measure,
                'quantity_requested' => (float) $it->quantity_requested,
                'quantity_ordered' => (float) $it->quantity_ordered,
                'remaining_quantity' => (float) $it->remainingQuantity(),
                'quantity_to_order' => (float) $it->remainingQuantity(),
                'unit_price' => (float) ($it->estimated_rate > 0 ? $it->estimated_rate : $it->material->standard_cost),
                'tax_percent' => 18.00,
            ])->values()->all();

        return view('purchase-requests.convert', compact('request', 'suppliers', 'warehouses', 'convertibleItems'));
    }

    public function storePOConversion(Request $httpRequest, PurchaseRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isProcurement = $user->hasPermission('*') || $user->hasPermission('procurement.*') || in_array($roleSlug, ['owner', 'admin', 'procurement_officer', 'procurement']);

        if (!$isProcurement) {
            abort(403, 'You do not have permission to convert purchase requests into purchase orders.');
        }

        if (!in_array($request->status, ['approved', 'partially_converted'])) {
            return redirect()->route('purchase-requests.show', $request)
                ->withErrors(['status' => 'Only approved purchase requests can be converted into purchase orders.']);
        }

        $validated = $httpRequest->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'po_date' => 'required|date',
            'delivery_due_date' => 'nullable|date|after_or_equal:po_date',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'terms_and_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.pr_item_id' => 'required|exists:purchase_request_items,id',
            'items.*.quantity_to_order' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|between:0,100',
        ]);

        $request->load('items');

        // Verify quantities and find at least one ordered item
        $totalQuantityToOrder = 0;
        foreach ($validated['items'] as $itemData) {
            $prItem = $request->items->firstWhere('id', $itemData['pr_item_id']);
            if (!$prItem) {
                return back()->withInput()->withErrors(['items' => 'Invalid PR line item.']);
            }

            $qtyToOrder = (float) $itemData['quantity_to_order'];
            $remaining = $prItem->remainingQuantity();

            if ($qtyToOrder > $remaining + 0.0001) {
                return back()->withInput()->withErrors([
                    'items' => "Ordered quantity ({$qtyToOrder}) exceeds remaining request quantity ({$remaining}) for {$prItem->material->name}.",
                ]);
            }

            $totalQuantityToOrder += $qtyToOrder;
        }

        if ($totalQuantityToOrder <= 0) {
            return back()->withInput()->withErrors([
                'items' => 'You must order at least one item with a quantity greater than zero.',
            ]);
        }

        $po = DB::transaction(function () use ($validated, $request) {
            $subtotal = 0.00;
            $totalTax = 0.00;

            // Compute totals first
            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity_to_order'];
                if ($qty <= 0) continue;

                $rate = (float) $itemData['unit_price'];
                $taxPct = (float) ($itemData['tax_percent'] ?? 18.00);

                $lineSub = $qty * $rate;
                $lineTax = $lineSub * ($taxPct / 100);

                $subtotal += $lineSub;
                $totalTax += $lineTax;
            }

            $totalAmount = $subtotal + $totalTax;

            $po = PurchaseOrder::create([
                'company_id' => $request->company_id,
                'supplier_id' => $validated['supplier_id'],
                'project_id' => $request->project_id,
                'purchase_request_id' => $request->id,
                'po_number' => 'PO-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'po_date' => $validated['po_date'],
                'delivery_due_date' => $validated['delivery_due_date'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total_amount' => $totalAmount,
                'status' => 'approved',
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => Carbon::now(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity_to_order'];
                if ($qty <= 0) continue;

                $prItem = $request->items->firstWhere('id', $itemData['pr_item_id']);
                $rate = (float) $itemData['unit_price'];
                $taxPct = (float) ($itemData['tax_percent'] ?? 18.00);
                $lineTotal = ($qty * $rate) * (1 + ($taxPct / 100));

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'material_id' => $prItem->material_id,
                    'purchase_request_item_id' => $prItem->id,
                    'quantity_ordered' => $qty,
                    'unit_price' => $rate,
                    'tax_percent' => $taxPct,
                    'total_price' => $lineTotal,
                    'quantity_received' => 0.000,
                ]);

                // Increment ordered quantity on PR line
                $prItem->increment('quantity_ordered', $qty);
            }

            // Update Purchase Request status
            $request->refresh();
            $allFullyOrdered = true;
            foreach ($request->items as $prItem) {
                if ($prItem->remainingQuantity() > 0.001) {
                    $allFullyOrdered = false;
                    break;
                }
            }

            $request->update([
                'status' => $allFullyOrdered ? 'po_created' : 'partially_converted',
            ]);

            // Audit
            AuditLog::create([
                'company_id' => $request->company_id,
                'user_id' => Auth::id(),
                'action' => 'purchase_order_created_from_request',
                'auditable_type' => PurchaseOrder::class,
                'auditable_id' => $po->id,
                'new_values' => [
                    'po_number' => $po->po_number,
                    'pr_number' => $request->pr_number,
                    'status' => $request->status,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $po;
        });

        return redirect()->route('procurement.show', $po)
            ->with('success', "Purchase Order {$po->po_number} successfully created and linked to {$request->pr_number}.");
    }
}
