<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Site;
use App\Models\SubcontractorWorkOrder;
use App\Models\Supplier;
use App\Services\SubcontractorBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubcontractorWorkOrderController extends Controller
{
    /**
     * Work Orders Register.
     */
    public function index(Request $request): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $query = SubcontractorWorkOrder::where('company_id', $companyId)
            ->with(['project', 'site', 'subcontractor', 'creator']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('subcontractor_id')) {
            $query->where('subcontractor_id', $request->subcontractor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('work_category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('wo_number', 'like', "%{$s}%")
                    ->orWhere('title', 'like', "%{$s}%")
                    ->orWhereHas('subcontractor', fn ($sub) => $sub->where('name', 'like', "%{$s}%"));
            });
        }

        $workOrders = $query->latest()->paginate(10)->withQueryString();

        $allOrders = SubcontractorWorkOrder::where('company_id', $companyId)->get();
        $metrics = [
            'total_count' => $allOrders->count(),
            'total_value' => $allOrders->sum(fn ($wo) => $wo->effectiveValue()),
            'total_certified' => $allOrders->sum(fn ($wo) => $wo->totalCertifiedValue()),
            'open_commitment' => $allOrders->whereIn('status', ['approved', 'active'])->sum(fn ($wo) => $wo->unbilledCommitment()),
            'active_count' => $allOrders->whereIn('status', ['approved', 'active'])->count(),
        ];

        $projects = Project::where('company_id', $companyId)->get(['id', 'name', 'code']);
        $subcontractors = Supplier::where('company_id', $companyId)->whereIn('vendor_type', ['subcontractor', 'both'])->get(['id', 'name', 'subcontractor_code']);

        return view('work-orders.index', compact('workOrders', 'metrics', 'projects', 'subcontractors'));
    }

    /**
     * Show Work Order creation form.
     */
    public function create(Request $request): View
    {
        $this->authorizeCreate();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $projects = Project::where('company_id', $companyId)->get(['id', 'name', 'code']);
        $sites = Site::where('company_id', $companyId)->get(['id', 'project_id', 'name']);
        $subcontractors = Supplier::where('company_id', $companyId)->whereIn('vendor_type', ['subcontractor', 'both'])->get();

        $selectedProjectId = $request->project_id ?? ($projects->first()?->id);
        $selectedSubcontractorId = $request->subcontractor_id;

        $count = SubcontractorWorkOrder::where('company_id', $companyId)->count() + 1;
        $suggestedWoNumber = 'WO-' . date('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        return view('work-orders.create', compact('projects', 'sites', 'subcontractors', 'selectedProjectId', 'selectedSubcontractorId', 'suggestedWoNumber'));
    }

    /**
     * Store newly created Work Order.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCreate();

        $user = Auth::user();

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'site_id' => 'nullable|exists:sites,id',
            'subcontractor_id' => 'required|exists:suppliers,id',
            'wo_number' => 'nullable|string|max:50',
            'title' => 'required|string|max:255',
            'work_category' => 'required|string|max:50',
            'work_package' => 'nullable|string|max:100',
            'scope_of_work' => 'nullable|string',
            'start_date' => 'required|date',
            'completion_date' => 'required|date|after_or_equal:start_date',
            'retention_percent' => 'required|numeric|min:0|max:50',
            'retention_cap_amount' => 'nullable|numeric|min:0',
            'tds_rate_percent' => 'required|numeric|min:0|max:30',
            'gst_rate_percent' => 'required|numeric|min:0|max:28',
            'payment_terms' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.category' => 'nullable|string',
            'items.*.unit_of_measure' => 'required|string|max:20',
            'items.*.approved_quantity' => 'required|numeric|min:0.001',
            'items.*.unit_rate' => 'required|numeric|min:0.01',
            'terms_and_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $workOrder = SubcontractorBillingService::createWorkOrder($validated, $user);

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', "Work Order {$workOrder->wo_number} created successfully with " . count($workOrder->items) . " BOQ lines.");
    }

    /**
     * Show Work Order details with BOQ, variations, and reconciliation.
     */
    public function show(SubcontractorWorkOrder $workOrder): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($workOrder->company_id !== $companyId) {
            abort(404);
        }

        $workOrder->load([
            'project',
            'site',
            'subcontractor',
            'creator',
            'approver',
            'items.measurementBookEntries',
            'variations.requester',
            'variations.approver',
            'bills.payments',
        ]);

        $recentMbEntries = $workOrder->measurementBookEntries()
            ->with(['measuredBy', 'workOrderItem'])
            ->latest()
            ->take(10)
            ->get();

        return view('work-orders.show', compact('workOrder', 'recentMbEntries'));
    }

    /**
     * Approve Work Order.
     */
    public function approve(SubcontractorWorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeApprove();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($workOrder->company_id !== $companyId) {
            abort(404);
        }

        SubcontractorBillingService::approveWorkOrder($workOrder, $user);

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', "Work Order {$workOrder->wo_number} has been officially approved for site execution.");
    }

    /**
     * Add a controlled variation / revision to Work Order.
     */
    public function storeVariation(Request $request, SubcontractorWorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeApprove();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($workOrder->company_id !== $companyId) {
            abort(404);
        }

        $validated = $request->validate([
            'variation_type' => 'required|in:quantity_increase,rate_revision,scope_addition,new_item',
            'work_order_item_id' => 'nullable|exists:subcontractor_work_order_items,id',
            'revised_quantity' => 'nullable|numeric|min:0.001',
            'revised_rate' => 'nullable|numeric|min:0.01',
            'value_impact' => 'nullable|numeric',
            'reason' => 'required|string|min:5',
        ]);

        $variation = SubcontractorBillingService::addWorkOrderVariation($workOrder, $validated, $user);

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', "Controlled Variation {$variation->variation_number} approved and applied to Work Order.");
    }

    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.view')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant', 'procurement_officer']))) {
            abort(403, 'Unauthorized to view Subcontractor Work Orders.');
        }
    }

    protected function authorizeCreate(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.create')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'procurement_officer']))) {
            abort(403, 'Unauthorized to create Subcontractor Work Orders.');
        }
    }

    protected function authorizeApprove(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.approve')
            && ! in_array($role, ['owner', 'admin', 'project_manager']))) {
            abort(403, 'Unauthorized: Only Project Managers or Admins may approve Work Orders or Variations.');
        }
    }
}
