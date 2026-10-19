<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\Expense;
use App\Models\LoanEmiSchedule;
use App\Models\MeasurementBookEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\SubcontractorBill;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $companyId = session('current_company_id') ?? $user?->currentCompany()?->id;

        if ($companyId && ! session()->has('current_company_id')) {
            session(['current_company_id' => $companyId]);
        }

        // Project Reconciliation
        // Active projects are ongoing/planned projects in the project register
        $activeProjectsQuery = Project::whereIn('status', ['planned', 'active', 'on_track', 'at_risk', 'delayed']);
        $completedProjectsQuery = Project::where('status', 'completed');
        if ($companyId) {
            $activeProjectsQuery->where('company_id', $companyId);
            $completedProjectsQuery->where('company_id', $companyId);
        }
        $activeProjectsCount = $activeProjectsQuery->count();
        $completedProjectsCount = $completedProjectsQuery->count();

        // Pending Approvals requiring actionable intervention
        $prQuery = PurchaseRequest::where('status', 'pending');
        $poQuery = PurchaseOrder::where('status', 'draft');
        $invQuery = SupplierInvoice::where('status', 'pending_approval');
        $scbQuery = SubcontractorBill::where('status', 'submitted');
        $mbQuery = MeasurementBookEntry::where('status', 'submitted');
        $expQuery = Expense::where('status', 'pending');
        $payrollQuery = PayrollPeriod::where('status', 'processing');

        if ($companyId) {
            $prQuery->where('company_id', $companyId);
            $poQuery->where('company_id', $companyId);
            $invQuery->where('company_id', $companyId);
            $scbQuery->where('company_id', $companyId);
            $mbQuery->where('company_id', $companyId);
            $expQuery->where('company_id', $companyId);
            $payrollQuery->where('company_id', $companyId);
        }

        $pendingPRsCount = $prQuery->count();
        $draftPOsCount = $poQuery->count();
        $pendingInvoicesCount = $invQuery->count();
        $submittedSubconBillsCount = $scbQuery->count();
        $submittedMBCount = $mbQuery->count();
        $pendingExpensesCount = $expQuery->count();
        $processingPayrollCount = $payrollQuery->count();

        $pendingApprovalsCount = $pendingPRsCount +
            $draftPOsCount +
            $pendingInvoicesCount +
            $submittedSubconBillsCount +
            $submittedMBCount +
            $pendingExpensesCount +
            $processingPayrollCount;

        // Receivables: balance due from clients on submitted, approved, or partially paid invoices
        $receivablesQuery = ClientInvoice::whereIn('status', ['submitted', 'approved', 'partially_paid']);
        if ($companyId) {
            $receivablesQuery->where('company_id', $companyId);
        }
        $totalReceivables = (float) ($receivablesQuery->selectRaw('SUM(total_amount - paid_amount) as total')->value('total') ?? 0.00);

        // Money Received: total collected from client bill payments
        $receivedQuery = ClientInvoice::query();
        if ($companyId) {
            $receivedQuery->where('company_id', $companyId);
        }
        $totalReceived = (float) ($receivedQuery->sum('paid_amount') ?? 0.00);

        // Payables:
        // 1. Supplier invoices balance due (materials, equipment rentals, supplies)
        $supplierPayablesQuery = SupplierInvoice::whereIn('status', ['pending_approval', 'approved', 'partially_paid']);
        if ($companyId) {
            $supplierPayablesQuery->where('company_id', $companyId);
        }
        $supplierPayables = (float) ($supplierPayablesQuery->selectRaw('SUM(total_amount - paid_amount) as total')->value('total') ?? 0.00);

        // 2. Subcontractor liabilities balance due (certified/approved/partially paid work orders, excluding retentions held)
        $subcontractorPayablesQuery = SubcontractorBill::whereIn('status', ['submitted', 'certified', 'approved_for_payment', 'partially_paid']);
        if ($companyId) {
            $subcontractorPayablesQuery->where('company_id', $companyId);
        }
        $subcontractorPayables = (float) ($subcontractorPayablesQuery->selectRaw('SUM(net_payable_amount - paid_amount) as total')->value('total') ?? 0.00);

        // Reconciled Total Payables: Supplier bills + Subcontractor liabilities without double counting
        $totalPayables = round($supplierPayables + $subcontractorPayables, 2);

        // Recorded Expenses (Approved operational and site expenses)
        $expenseQuery = Expense::where('status', 'approved');
        if ($companyId) {
            $expenseQuery->where('company_id', $companyId);
        }
        $totalExpenses = (float) ($expenseQuery->sum('amount') ?? 0.00);

        // Recent Projects query (scoped to company)
        $projectsQuery = Project::with(['client', 'manager']);
        if ($companyId) {
            $projectsQuery->where('company_id', $companyId);
        }
        $projects = $projectsQuery->latest()->take(6)->get();

        // Site Operations with active workers
        $sitesQuery = Site::with(['project', 'supervisor', 'dailyLogs' => function ($q) {
            $q->latest('log_date')->take(1);
        }]);
        if ($companyId) {
            $sitesQuery->where('company_id', $companyId);
        }
        $sites = $sitesQuery->get();

        // Project timeline highlights
        $timelineQuery = Project::whereIn('status', ['on_track', 'at_risk', 'delayed']);
        if ($companyId) {
            $timelineQuery->where('company_id', $companyId);
        }
        $timelineProjects = $timelineQuery->latest()->take(4)->get();

        // Needs Attention & Upcoming Payments
        $overdueBillsQuery = SupplierInvoice::where('status', 'approved')
            ->where('due_date', '<=', Carbon::now()->addDays(7))
            ->with('supplier');
        if ($companyId) {
            $overdueBillsQuery->where('company_id', $companyId);
        }
        $overdueBills = $overdueBillsQuery->take(3)->get();

        $upcomingEmisQuery = LoanEmiSchedule::where('status', 'pending')
            ->where('due_date', '<=', Carbon::now()->addDays(14))
            ->whereHas('loan', function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->with('loan');
        $upcomingEmis = $upcomingEmisQuery->take(3)->get();

        return view('dashboard.index', compact(
            'activeProjectsCount',
            'completedProjectsCount',
            'pendingApprovalsCount',
            'pendingPRsCount',
            'draftPOsCount',
            'pendingInvoicesCount',
            'submittedSubconBillsCount',
            'submittedMBCount',
            'pendingExpensesCount',
            'processingPayrollCount',
            'totalReceivables',
            'totalPayables',
            'supplierPayables',
            'subcontractorPayables',
            'totalReceived',
            'totalExpenses',
            'projects',
            'sites',
            'timelineProjects',
            'overdueBills',
            'upcomingEmis'
        ));
    }
}
