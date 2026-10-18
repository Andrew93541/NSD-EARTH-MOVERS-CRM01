<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubcontractorController extends Controller
{
    /**
     * Subcontractor Register listing.
     */
    public function index(Request $request): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $query = Supplier::where('company_id', $companyId)
            ->whereIn('vendor_type', ['subcontractor', 'both']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $cat = $request->category;
            $query->whereJsonContains('work_categories', $cat);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('subcontractor_code', 'like', "%{$s}%")
                    ->orWhere('contact_person', 'like', "%{$s}%")
                    ->orWhere('gstin', 'like', "%{$s}%")
                    ->orWhere('pan', 'like', "%{$s}%");
            });
        }

        $subcontractors = $query->withCount(['subcontractorWorkOrders', 'measurementBookEntries', 'subcontractorBills'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total_count' => Supplier::where('company_id', $companyId)->whereIn('vendor_type', ['subcontractor', 'both'])->count(),
            'active_count' => Supplier::where('company_id', $companyId)->whereIn('vendor_type', ['subcontractor', 'both'])->where('status', 'active')->count(),
            'earthwork_count' => Supplier::where('company_id', $companyId)->whereIn('vendor_type', ['subcontractor', 'both'])->whereJsonContains('work_categories', 'earthwork')->count(),
            'civil_count' => Supplier::where('company_id', $companyId)->whereIn('vendor_type', ['subcontractor', 'both'])->whereJsonContains('work_categories', 'civil')->count(),
        ];

        return view('subcontractors.index', compact('subcontractors', 'stats'));
    }

    /**
     * Show create subcontractor form.
     */
    public function create(): View
    {
        $this->authorizeManage();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        // Next code preview
        $count = Supplier::where('company_id', $companyId)->whereNotNull('subcontractor_code')->count() + 1;
        $suggestedCode = 'SC-' . date('Y') . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        return view('subcontractors.create', compact('suggestedCode'));
    }

    /**
     * Store newly created subcontractor.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subcontractor_code' => 'nullable|string|max:50',
            'vendor_type' => 'required|in:subcontractor,both',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:20',
            'tds_section' => 'required|string|max:50',
            'trade_license_no' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'payment_terms' => 'required|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'work_categories' => 'nullable|array',
            'work_categories.*' => 'string',
            'status' => 'required|in:active,inactive',
        ]);

        if (empty($validated['subcontractor_code'])) {
            $count = Supplier::where('company_id', $companyId)->whereNotNull('subcontractor_code')->count() + 1;
            $validated['subcontractor_code'] = 'SC-' . date('Y') . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        }

        $validated['company_id'] = $companyId;

        $subcontractor = Supplier::create($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'action' => 'create_subcontractor',
            'auditable_type' => Supplier::class,
            'auditable_id' => $subcontractor->id,
            'new_values' => [
                'name' => $subcontractor->name,
                'code' => $subcontractor->subcontractor_code,
            ],
        ]);

        return redirect()->route('subcontractors.show', $subcontractor)
            ->with('success', "Subcontractor {$subcontractor->name} registered successfully.");
    }

    /**
     * Show detailed profile of subcontractor.
     */
    public function show(Supplier $subcontractor): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($subcontractor->company_id !== $companyId) {
            abort(404);
        }

        $workOrders = $subcontractor->subcontractorWorkOrders()
            ->with(['project', 'site'])
            ->latest()
            ->get();

        $bills = $subcontractor->subcontractorBills()
            ->with(['project', 'workOrder'])
            ->latest()
            ->take(10)
            ->get();

        $mbEntries = $subcontractor->measurementBookEntries()
            ->with(['project', 'workOrder', 'workOrderItem'])
            ->latest()
            ->take(10)
            ->get();

        $financials = [
            'total_order_value' => $workOrders->sum(fn ($wo) => $wo->effectiveValue()),
            'total_certified_gross' => $bills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('gross_work_value'),
            'total_retention_held' => $bills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('retention_amount'),
            'total_paid' => $bills->sum('paid_amount'),
            'total_outstanding' => $bills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid'])->sum(fn ($b) => $b->outstandingBalance()),
        ];

        return view('subcontractors.show', compact('subcontractor', 'workOrders', 'bills', 'mbEntries', 'financials'));
    }

    /**
     * Authorize viewing subcontractors.
     */
    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('subcontractors.view')
            && ! $user->hasPermission('procurement.*')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant', 'procurement_officer']))) {
            abort(403, 'Unauthorized access to Subcontractors Register.');
        }
    }

    /**
     * Authorize managing subcontractors.
     */
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('subcontractors.manage')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'accountant', 'procurement_officer']))) {
            abort(403, 'Unauthorized to create or modify Subcontractor records.');
        }
    }
}
