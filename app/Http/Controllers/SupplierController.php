<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Material Supplier directory listing.
     */
    public function index(Request $request): View
    {
        $this->authorizeView();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        // Filter material suppliers (supplier, both) - excluding pure subcontractors
        $query = Supplier::where('company_id', $companyId)
            ->whereIn('vendor_type', ['supplier', 'both']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('vendor_type')) {
            $query->where('vendor_type', $request->vendor_type);
        }

        if ($request->filled('payment_terms')) {
            $query->where('payment_terms', $request->payment_terms);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('contact_person', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('gstin', 'like', "%{$s}%")
                    ->orWhere('pan', 'like', "%{$s}%")
                    ->orWhere('bank_name', 'like', "%{$s}%");
            });
        }

        $suppliers = $query->withCount(['purchaseOrders', 'invoices'])
            ->with(['purchaseOrders' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }, 'invoices' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $allSuppliers = Supplier::where('company_id', $companyId)
            ->whereIn('vendor_type', ['supplier', 'both'])
            ->with(['purchaseOrders', 'invoices'])
            ->get();

        $totalInvoiced = (float) $allSuppliers->sum(fn ($s) => $s->invoices->sum('total_amount'));
        $totalPaid = (float) $allSuppliers->sum(fn ($s) => $s->invoices->sum('paid_amount'));
        $totalPoValue = (float) $allSuppliers->sum(fn ($s) => $s->purchaseOrders->whereIn('status', ['approved', 'sent', 'partially_received', 'completed'])->sum('total_amount'));

        $stats = [
            'total_count' => $allSuppliers->count(),
            'active_count' => $allSuppliers->where('status', 'active')->count(),
            'pure_suppliers_count' => $allSuppliers->where('vendor_type', 'supplier')->count(),
            'both_count' => $allSuppliers->where('vendor_type', 'both')->count(),
            'total_po_value' => $totalPoValue,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding_payable' => max(0.0, $totalInvoiced - $totalPaid),
        ];

        return view('suppliers.index', compact('suppliers', 'stats'));
    }

    /**
     * Show create material supplier form.
     */
    public function create(): View
    {
        $this->authorizeManage();

        return view('suppliers.create');
    }

    /**
     * Store newly created material supplier.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $user = Auth::user();
        $companyId = session('current_company_id') ?? $user?->currentCompany()?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_type' => 'required|in:supplier,both',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gstin' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'gstin')->where('company_id', $companyId),
            ],
            'pan' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'payment_terms' => 'required|in:immediate,15_days,30_days,45_days',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['company_id'] = $companyId;

        $supplier = Supplier::create($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'action' => 'create_supplier',
            'auditable_type' => Supplier::class,
            'auditable_id' => $supplier->id,
            'new_values' => [
                'name' => $supplier->name,
                'vendor_type' => $supplier->vendor_type,
                'gstin' => $supplier->gstin,
            ],
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', "Material Supplier '{$supplier->name}' registered successfully.");
    }

    /**
     * Show supplier profile, purchase orders, deliveries, and invoices.
     */
    public function show(Supplier $supplier): View
    {
        $this->authorizeView();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($supplier->company_id !== $companyId) {
            abort(404, 'Material Supplier not found in active company.');
        }

        // If vendor is pure subcontractor, redirect to subcontractors master
        if ($supplier->vendor_type === 'subcontractor') {
            return redirect()->route('subcontractors.show', $supplier);
        }

        $purchaseOrders = $supplier->purchaseOrders()
            ->where('company_id', $companyId)
            ->with(['project'])
            ->latest()
            ->get();

        $poIds = $purchaseOrders->pluck('id');

        $goodsReceipts = GoodsReceipt::whereIn('purchase_order_id', $poIds)
            ->where('company_id', $companyId)
            ->with(['warehouse', 'purchaseOrder.project'])
            ->latest()
            ->get();

        $invoices = $supplier->invoices()
            ->where('company_id', $companyId)
            ->with(['purchaseOrder'])
            ->latest()
            ->get();

        $poCommitted = (float) $purchaseOrders->whereIn('status', ['approved', 'sent', 'partially_received', 'completed'])->sum('total_amount');
        $totalInvoiced = (float) $invoices->sum('total_amount');
        $totalPaid = (float) $invoices->sum('paid_amount');
        $outstandingPayable = max(0.0, $totalInvoiced - $totalPaid);

        $financials = [
            'po_committed' => $poCommitted,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding_payable' => $outstandingPayable,
            'open_orders_count' => $purchaseOrders->whereIn('status', ['approved', 'sent', 'partially_received'])->count(),
            'total_grns_count' => $goodsReceipts->count(),
        ];

        return view('suppliers.show', compact('supplier', 'purchaseOrders', 'goodsReceipts', 'invoices', 'financials'));
    }

    /**
     * Show edit material supplier form.
     */
    public function edit(Supplier $supplier): View
    {
        $this->authorizeManage();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($supplier->company_id !== $companyId) {
            abort(404, 'Material Supplier not found in active company.');
        }

        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update material supplier details.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeManage();

        $user = Auth::user();
        $companyId = session('current_company_id') ?? $user?->currentCompany()?->id;

        if ($supplier->company_id !== $companyId) {
            abort(404, 'Material Supplier not found in active company.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_type' => 'required|in:supplier,both',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gstin' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'gstin')->where('company_id', $companyId)->ignore($supplier->id),
            ],
            'pan' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'payment_terms' => 'required|in:immediate,15_days,30_days,45_days',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $oldValues = [
            'name' => $supplier->name,
            'vendor_type' => $supplier->vendor_type,
            'gstin' => $supplier->gstin,
            'status' => $supplier->status,
        ];

        $supplier->update($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'action' => 'update_supplier',
            'auditable_type' => Supplier::class,
            'auditable_id' => $supplier->id,
            'old_values' => $oldValues,
            'new_values' => [
                'name' => $supplier->name,
                'vendor_type' => $supplier->vendor_type,
                'gstin' => $supplier->gstin,
                'status' => $supplier->status,
            ],
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', "Material Supplier '{$supplier->name}' updated successfully.");
    }

    /**
     * Authorize viewing material suppliers.
     */
    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('suppliers.view')
            && ! $user->hasPermission('suppliers.*')
            && ! $user->hasPermission('procurement.*')
            && ! $user->hasPermission('materials.*')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'procurement_officer', 'storekeeper', 'accountant']))) {
            abort(403, 'Unauthorized access to Material Suppliers Directory.');
        }
    }

    /**
     * Authorize managing material suppliers.
     */
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('suppliers.manage')
            && ! $user->hasPermission('suppliers.*')
            && ! $user->hasPermission('procurement.*')
            && ! in_array($role, ['owner', 'admin', 'procurement_officer', 'accountant']))) {
            abort(403, 'Unauthorized to create or modify Material Supplier records.');
        }
    }
}
