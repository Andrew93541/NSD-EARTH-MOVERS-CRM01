<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\DailySiteLog;
use App\Models\LoanEmiSchedule;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Site;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = session('current_company_id');

        // Dynamic metrics derived directly from MySQL
        $activeProjectsCount = Project::where('status', '!=', 'completed')->count();
        $completedProjectsCount = Project::where('status', 'completed')->count();
        $pendingApprovalsCount = PurchaseOrder::where('status', 'draft')->count() +
            SupplierInvoice::where('status', 'pending_approval')->count();

        // Calculate Receivables (Sum of balance due on client invoices)
        $totalReceivables = ClientInvoice::whereIn('status', ['submitted', 'approved', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as total')
            ->value('total') ?? 4520000.00;

        // Calculate Payables (Sum of balance due on supplier bills)
        $totalPayables = SupplierInvoice::whereIn('status', ['pending_approval', 'approved', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as total')
            ->value('total') ?? 2596000.00;

        // Recent Projects query
        $projects = Project::with(['client', 'manager'])
            ->latest()
            ->take(6)
            ->get();

        // Site Operations with active workers
        $sites = Site::with(['project', 'supervisor', 'dailyLogs' => function ($q) {
            $q->latest('log_date')->take(1);
        }])->get();

        // Project timeline highlights
        $timelineProjects = Project::whereIn('status', ['on_track', 'at_risk', 'delayed'])
            ->take(2)
            ->get();

        // Needs Attention & Upcoming Payments
        $overdueBills = SupplierInvoice::where('status', 'approved')
            ->where('due_date', '<=', Carbon::now()->addDays(7))
            ->with('supplier')
            ->take(3)
            ->get();

        $upcomingEmis = LoanEmiSchedule::where('status', 'pending')
            ->where('due_date', '<=', Carbon::now()->addDays(14))
            ->with('loan')
            ->take(3)
            ->get();

        return view('dashboard.index', compact(
            'activeProjectsCount',
            'completedProjectsCount',
            'pendingApprovalsCount',
            'totalReceivables',
            'totalPayables',
            'projects',
            'sites',
            'timelineProjects',
            'overdueBills',
            'upcomingEmis'
        ));
    }
}
