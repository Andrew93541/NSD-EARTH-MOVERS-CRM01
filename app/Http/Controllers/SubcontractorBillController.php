<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\MeasurementBookEntry;
use App\Models\Project;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorWorkOrder;
use App\Services\SubcontractorBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubcontractorBillController extends Controller
{
    /**
     * Subcontractor RA Bills Register.
     */
    public function index(Request $request): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $query = SubcontractorBill::where('company_id', $companyId)
            ->with(['project', 'subcontractor', 'workOrder', 'creator', 'certifier', 'journalEntry']);

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
                $q->where('bill_number', 'like', "%{$s}%")
                    ->orWhereHas('subcontractor', fn ($sub) => $sub->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('workOrder', fn ($wo) => $wo->where('wo_number', 'like', "%{$s}%"));
            });
        }

        $bills = $query->latest('bill_date')->paginate(10)->withQueryString();

        $allBills = SubcontractorBill::where('company_id', $companyId)->get();
        $stats = [
            'total_bills' => $allBills->count(),
            'total_certified_value' => $allBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('gross_work_value'),
            'total_retention_held' => $allBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('retention_amount'),
            'total_net_payable' => $allBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('net_payable_amount'),
            'total_paid' => $allBills->sum('paid_amount'),
            'total_outstanding' => $allBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid'])->sum(fn ($b) => $b->outstandingBalance()),
        ];

        $projects = Project::where('company_id', $companyId)->get(['id', 'name', 'code']);
        $workOrders = SubcontractorWorkOrder::where('company_id', $companyId)->get(['id', 'wo_number', 'title']);

        return view('subcontractor-bills.index', compact('bills', 'stats', 'projects', 'workOrders'));
    }

    /**
     * Show form to generate bill from approved MB entries.
     */
    public function create(Request $request): View
    {
        $this->authorizePrepare();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        $workOrders = SubcontractorWorkOrder::where('company_id', $companyId)
            ->whereIn('status', ['approved', 'active'])
            ->with(['project', 'subcontractor', 'items'])
            ->get();

        $selectedWoId = $request->work_order_id ?? ($workOrders->first()?->id);
        $selectedWo = $workOrders->firstWhere('id', $selectedWoId) ?? $workOrders->first();

        $unbilledMbEntries = collect();
        if ($selectedWo) {
            $unbilledMbEntries = MeasurementBookEntry::where('work_order_id', $selectedWo->id)
                ->where('status', 'approved')
                ->whereNull('certified_in_bill_id')
                ->with(['workOrderItem', 'measuredBy'])
                ->orderBy('measurement_date')
                ->get();
        }

        $nextSequence = $selectedWo ? (SubcontractorBill::where('work_order_id', $selectedWo->id)->count() + 1) : 1;
        $suggestedBillNumber = $selectedWo ? ('RA-' . str_pad((string) $nextSequence, 2, '0', STR_PAD_LEFT) . '-' . $selectedWo->wo_number) : 'RA-01';

        return view('subcontractor-bills.create', compact('workOrders', 'selectedWo', 'unbilledMbEntries', 'suggestedBillNumber', 'nextSequence'));
    }

    /**
     * Store new Subcontractor RA Bill from approved MB entries.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizePrepare();

        $user = Auth::user();

        $validated = $request->validate([
            'work_order_id' => 'required|exists:subcontractor_work_orders,id',
            'bill_number' => 'nullable|string|max:50',
            'bill_date' => 'required|date',
            'period_start_date' => 'nullable|date',
            'period_end_date' => 'nullable|date|after_or_equal:period_start_date',
            'measurement_entry_ids' => 'required|array|min:1',
            'measurement_entry_ids.*' => 'exists:measurement_book_entries,id',
            'retention_rate_percent' => 'nullable|numeric|min:0|max:50',
            'tds_rate_percent' => 'nullable|numeric|min:0|max:30',
            'gst_rate_percent' => 'nullable|numeric|min:0|max:28',
            'advance_recovery_amount' => 'nullable|numeric|min:0',
            'other_deductions_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $workOrder = SubcontractorWorkOrder::findOrFail($validated['work_order_id']);

        $bill = SubcontractorBillingService::createBillFromMeasurements(
            $workOrder,
            $validated['measurement_entry_ids'],
            $validated,
            $user
        );

        return redirect()->route('subcontractor-bills.show', $bill)
            ->with('success', "Subcontractor RA Bill {$bill->bill_number} generated successfully from " . count($validated['measurement_entry_ids']) . " approved measurements.");
    }

    /**
     * Detailed 3-Way Reconciliation View: Work Order BOQ vs MB vs Bill.
     */
    public function show(SubcontractorBill $subcontractorBill): View
    {
        $this->authorizeView();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($subcontractorBill->company_id !== $companyId) {
            abort(404);
        }

        $subcontractorBill->load([
            'project',
            'subcontractor',
            'workOrder.items',
            'items.workOrderItem',
            'measurementsPivot.measurementBookEntry.workOrderItem',
            'creator',
            'certifier',
            'approver',
            'journalEntry.lines.account',
            'payments.bankAccount',
            'payments.creator',
        ]);

        $bankAccounts = BankAccount::where('company_id', $companyId)->get();

        return view('subcontractor-bills.show', compact('subcontractorBill', 'bankAccounts'));
    }

    /**
     * Certify Subcontractor Bill and trigger Double-Entry Journal Posting.
     */
    public function certify(SubcontractorBill $subcontractorBill): RedirectResponse
    {
        $this->authorizeCertify();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($subcontractorBill->company_id !== $companyId) {
            abort(404);
        }

        SubcontractorBillingService::certifyBill($subcontractorBill, $user);

        return redirect()->route('subcontractor-bills.show', $subcontractorBill)
            ->with('success', "Subcontractor Bill {$subcontractorBill->bill_number} certified successfully. General Ledger journal posted (Dr 5040, Dr 1030 / Cr 2030, Cr 2040, Cr 2020).");
    }

    /**
     * Record payment against certified bill.
     */
    public function recordPayment(Request $request, SubcontractorBill $subcontractorBill): RedirectResponse
    {
        $this->authorizePayment();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($subcontractorBill->company_id !== $companyId) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_mode' => 'required|string|max:50',
            'transaction_reference' => 'required|string|max:100',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string',
        ]);

        $payment = SubcontractorBillingService::recordBillPayment($subcontractorBill, $validated, $user);

        return redirect()->route('subcontractor-bills.show', $subcontractorBill)
            ->with('success', "Payment of ₹" . number_format($payment->amount, 2) . " recorded against {$subcontractorBill->bill_number}. Bank journal entry posted.");
    }

    /**
     * Reverse certified bill through controlled reversing journal.
     */
    public function reverse(Request $request, SubcontractorBill $subcontractorBill): RedirectResponse
    {
        $this->authorizeCertify();

        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id');

        if ($subcontractorBill->company_id !== $companyId) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        SubcontractorBillingService::reverseBill($subcontractorBill, $validated['reason'], $user);

        return redirect()->route('subcontractor-bills.show', $subcontractorBill)
            ->with('warning', "Bill {$subcontractorBill->bill_number} has been reversed. Counter-journal posted and measurements unlocked.");
    }

    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('subcontractor_bills.view')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'accountant']))) {
            abort(403, 'Unauthorized access to Subcontractor Billing.');
        }
    }

    protected function authorizePrepare(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('subcontractor_bills.prepare')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'accountant']))) {
            abort(403, 'Unauthorized: Only Project Managers, Accountants, or Admins may generate subcontractor bills.');
        }
    }

    protected function authorizeCertify(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('subcontractor_bills.certify')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'accountant']))) {
            abort(403, 'Unauthorized: Only authorized Project Managers or Commercial Directors may certify subcontractor bills.');
        }
    }

    protected function authorizePayment(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('subcontractor_bills.pay')
            && ! in_array($role, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized: Only Accountants or Finance Admins may record payments against subcontractor bills.');
        }
    }
}
