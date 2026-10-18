<?php

namespace App\Http\Controllers;

use App\Models\MeasurementBookEntry;
use App\Models\Project;
use App\Models\Site;
use App\Models\SubcontractorWorkOrder;
use App\Services\SubcontractorBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MeasurementBookController extends Controller
{
    /**
     * Measurement Book (MB) Register.
     */
    public function index(Request $request): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $query = MeasurementBookEntry::where('company_id', $companyId)
            ->with(['workOrder', 'workOrderItem', 'project', 'site', 'subcontractor', 'measuredBy', 'reviewedBy']);

        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->work_order_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('mb_number', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('location_chainage', 'like', "%{$s}%")
                    ->orWhereHas('subcontractor', fn ($sub) => $sub->where('name', 'like', "%{$s}%"));
            });
        }

        $mbEntries = $query->latest('measurement_date')->paginate(12)->withQueryString();

        $allEntries = MeasurementBookEntry::where('company_id', $companyId)->get();
        $stats = [
            'total_entries' => $allEntries->count(),
            'submitted_pending_review' => $allEntries->where('status', 'submitted')->count(),
            'approved_ready_to_bill' => $allEntries->where('status', 'approved')->whereNull('certified_in_bill_id')->count(),
            'certified' => $allEntries->where('status', 'certified')->count(),
            'rejected' => $allEntries->where('status', 'rejected')->count(),
        ];

        $projects = Project::where('company_id', $companyId)->get(['id', 'name', 'code']);
        $workOrders = SubcontractorWorkOrder::where('company_id', $companyId)->whereIn('status', ['approved', 'active'])->get(['id', 'wo_number', 'title']);

        return view('measurement-books.index', compact('mbEntries', 'stats', 'projects', 'workOrders'));
    }

    /**
     * Show form to record a new site measurement.
     */
    public function create(Request $request): View
    {
        $this->authorizeRecord();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $workOrders = SubcontractorWorkOrder::where('company_id', $companyId)
            ->whereIn('status', ['approved', 'active'])
            ->with(['items', 'project', 'site', 'subcontractor'])
            ->get();

        $selectedWoId = $request->work_order_id ?? ($workOrders->first()?->id);
        $selectedWo = $workOrders->firstWhere('id', $selectedWoId) ?? $workOrders->first();

        $count = MeasurementBookEntry::where('company_id', $companyId)->count() + 1;
        $suggestedMbNumber = 'MB-' . date('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        return view('measurement-books.create', compact('workOrders', 'selectedWo', 'suggestedMbNumber'));
    }

    /**
     * Store new measurement entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRecord();

        $user = Auth::user();

        $validated = $request->validate([
            'work_order_id' => 'required|exists:subcontractor_work_orders,id',
            'work_order_item_id' => 'required|exists:subcontractor_work_order_items,id',
            'project_id' => 'required|exists:projects,id',
            'site_id' => 'nullable|exists:sites,id',
            'measurement_date' => 'required|date',
            'period_start_date' => 'nullable|date',
            'period_end_date' => 'nullable|date|after_or_equal:period_start_date',
            'location_chainage' => 'nullable|string|max:100',
            'description' => 'required|string',
            'measurement_formula' => 'required|in:dimensions_l_b_h,area_depth_a_d,unit_count_nos,direct_manual',
            'length' => 'nullable|numeric|min:0',
            'breadth' => 'nullable|numeric|min:0',
            'depth_height' => 'nullable|numeric|min:0',
            'multiplier_units' => 'nullable|numeric|min:0.01',
            'manual_quantity_override' => 'nullable|numeric|min:0.001',
            'override_reason' => 'nullable|string',
            'site_remarks' => 'nullable|string',
            'status' => 'required|in:draft,submitted',
        ]);

        $entry = SubcontractorBillingService::recordMeasurement($validated, $user);

        return redirect()->route('measurement-books.show', $entry)
            ->with('success', "Measurement Book entry {$entry->mb_number} ({$entry->measured_quantity} {$entry->workOrderItem->unit_of_measure}) recorded successfully.");
    }

    /**
     * Show measurement details, dimensions, and approval trail.
     */
    public function show(MeasurementBookEntry $measurementBook): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($measurementBook->company_id !== $companyId) {
            abort(404);
        }

        $measurementBook->load([
            'workOrder',
            'workOrderItem',
            'project',
            'site',
            'subcontractor',
            'measuredBy',
            'reviewedBy',
            'certifiedInBill',
        ]);

        // Prior and subsequent measurements for the same item
        $relatedEntries = MeasurementBookEntry::where('work_order_item_id', $measurementBook->work_order_item_id)
            ->where('id', '!=', $measurementBook->id)
            ->latest('measurement_date')
            ->take(5)
            ->get();

        return view('measurement-books.show', compact('measurementBook', 'relatedEntries'));
    }

    /**
     * Review and approve measurement.
     */
    public function approve(MeasurementBookEntry $measurementBook): RedirectResponse
    {
        $this->authorizeReview();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($measurementBook->company_id !== $companyId) {
            abort(404);
        }

        SubcontractorBillingService::approveMeasurement($measurementBook, $user);

        return redirect()->route('measurement-books.show', $measurementBook)
            ->with('success', "Measurement Book entry {$measurementBook->mb_number} approved and is now eligible for billing.");
    }

    /**
     * Reject measurement with mandatory reason.
     */
    public function reject(Request $request, MeasurementBookEntry $measurementBook): RedirectResponse
    {
        $this->authorizeReview();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($measurementBook->company_id !== $companyId) {
            abort(404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:5',
        ]);

        SubcontractorBillingService::rejectMeasurement($measurementBook, $validated['rejection_reason'], $user);

        return redirect()->route('measurement-books.show', $measurementBook)
            ->with('warning', "Measurement {$measurementBook->mb_number} rejected. Reason logged for correction.");
    }

    /**
     * Correct and resubmit rejected measurement.
     */
    public function resubmit(Request $request, MeasurementBookEntry $measurementBook): RedirectResponse
    {
        $this->authorizeRecord();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($measurementBook->company_id !== $companyId) {
            abort(404);
        }

        $validated = $request->validate([
            'description' => 'nullable|string',
            'measurement_formula' => 'required|in:dimensions_l_b_h,area_depth_a_d,unit_count_nos,direct_manual',
            'length' => 'nullable|numeric|min:0',
            'breadth' => 'nullable|numeric|min:0',
            'depth_height' => 'nullable|numeric|min:0',
            'multiplier_units' => 'nullable|numeric|min:0.01',
            'manual_quantity_override' => 'nullable|numeric|min:0.001',
            'override_reason' => 'nullable|string',
        ]);

        SubcontractorBillingService::resubmitMeasurement($measurementBook, $validated, $user);

        return redirect()->route('measurement-books.show', $measurementBook)
            ->with('success', "Measurement Book entry {$measurementBook->mb_number} corrected and resubmitted for verification.");
    }

    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('measurement_book.view')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant']))) {
            abort(403, 'Unauthorized access to Measurement Book.');
        }
    }

    protected function authorizeRecord(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('measurement_book.record')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'site_supervisor']))) {
            abort(403, 'Unauthorized: Only Site Supervisors, Project Managers, or Admins may record measurements.');
        }
    }

    protected function authorizeReview(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('measurement_book.review')
            && ! in_array($role, ['owner', 'admin', 'project_manager']))) {
            abort(403, 'Unauthorized: Only Project Managers or Admins may verify and approve site measurements.');
        }
    }
}
