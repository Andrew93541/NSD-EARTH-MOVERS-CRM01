<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $query = Project::where('company_id', $companyId)->with(['client', 'manager', 'sites']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('project_type', $request->type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $projects = $query->latest()->paginate(10)->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorizeManage();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $clients = Client::where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get();
        $managers = User::whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))->orderBy('name')->get();

        return view('projects.create', compact('clients', 'managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('projects', 'code')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'project_type' => 'required|in:civil,interior,maintenance,earthmoving',
            'location' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            'contract_value' => 'required|numeric|min:0',
            'approved_budget' => 'required|numeric|min:0',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $validated['company_id'] = $companyId;
        $validated['status'] = 'on_track';
        $validated['progress_percent'] = 0;

        $project = Project::create($validated);

        return redirect()->route('projects.show', $project)->with('success', 'Project created successfully.');
    }

    public function show(Project $project): View
    {
        $this->authorizeView();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($project->company_id !== $companyId) {
            abort(404, 'Project not found in active company.');
        }

        $project->load([
            'client',
            'manager',
            'sites.dailyLogs',
            'milestones',
            'tasks.assignee',
        ]);

        // Eager load records belonging strictly to this project and company
        $clientInvoices = $project->invoices()
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        $purchaseOrders = $project->purchaseOrders()
            ->where('company_id', $companyId)
            ->with('supplier')
            ->latest()
            ->get();

        $subWorkOrders = $project->subcontractorWorkOrders()
            ->where('company_id', $companyId)
            ->with('subcontractor')
            ->latest()
            ->get();

        $subBills = $project->subcontractorBills()
            ->where('company_id', $companyId)
            ->with(['subcontractor', 'workOrder'])
            ->latest()
            ->get();

        $expenses = Expense::where('project_id', $project->id)
            ->where('company_id', $companyId)
            ->with(['category', 'submitter'])
            ->latest('expense_date')
            ->get();

        $poIds = $purchaseOrders->pluck('id');
        $supplierInvoices = SupplierInvoice::whereIn('purchase_order_id', $poIds)
            ->where('company_id', $companyId)
            ->with('supplier')
            ->latest()
            ->get();

        $pendingPRs = PurchaseRequest::where('project_id', $project->id)
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'submitted', 'under_review'])
            ->latest()
            ->get();

        // 1. Contract & Billing metrics
        $contractValue = (float) $project->contract_value;
        $approvedBudget = (float) $project->approved_budget;
        $clientBillsRaised = (float) $clientInvoices->whereIn('status', ['submitted', 'approved', 'partially_paid', 'paid', 'overdue'])->sum('total_amount');
        $draftBillsAmount = (float) $clientInvoices->where('status', 'draft')->sum('total_amount');
        $moneyReceived = (float) $clientInvoices->sum('paid_amount');
        $outstandingClientBalance = max(0.0, (float) ($clientBillsRaised - $moneyReceived));
        $unbilledContractValue = max(0.0, (float) ($contractValue - $clientBillsRaised));

        // 2. Direct site expenses
        $recordedDirectExpensesPaid = (float) $expenses->whereIn('status', ['approved', 'reimbursed'])->sum('amount');
        $pendingDirectExpenses = (float) $expenses->where('status', 'pending')->sum('amount');

        // 3. Material procurement costs (Separating committed POs from actual paid vendor invoices)
        $committedProcurementPO = (float) $purchaseOrders->whereIn('status', ['approved', 'sent', 'partially_received', 'completed'])->sum('total_amount');
        $supplierInvoicedAmount = (float) $supplierInvoices->sum('total_amount');
        $supplierPaidAmount = (float) $supplierInvoices->sum('paid_amount');
        $supplierOutstandingPayable = max(0.0, (float) ($supplierInvoicedAmount - $supplierPaidAmount));

        // 4. Subcontractor obligations (Separated from material suppliers)
        $committedSubcontractorWO = (float) $subWorkOrders->where('status', 'active')->sum(fn ($wo) => $wo->effectiveValue());
        $subGrossCertified = (float) $subBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('gross_work_value');
        $subNetPayable = (float) $subBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])->sum('net_payable_amount');
        $subPaidAmount = (float) $subBills->sum('paid_amount');
        $subOutstandingPayable = (float) $subBills->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid'])->sum(fn ($b) => $b->outstandingBalance());

        // 5. Cumulative cash outflows paid to date
        $totalActualCashPaid = $recordedDirectExpensesPaid + $supplierPaidAmount + $subPaidAmount;

        $financials = [
            'contract_value' => $contractValue,
            'approved_budget' => $approvedBudget,
            'client_bills_raised' => $clientBillsRaised,
            'draft_client_bills' => $draftBillsAmount,
            'money_received' => $moneyReceived,
            'outstanding_client_balance' => $outstandingClientBalance,
            'unbilled_contract_value' => $unbilledContractValue,

            'recorded_direct_expenses_paid' => $recordedDirectExpensesPaid,
            'pending_direct_expenses' => $pendingDirectExpenses,

            'committed_procurement_po' => $committedProcurementPO,
            'supplier_invoiced' => $supplierInvoicedAmount,
            'supplier_paid' => $supplierPaidAmount,
            'supplier_outstanding' => $supplierOutstandingPayable,

            'committed_subcontractor_wo' => $committedSubcontractorWO,
            'sub_gross_certified' => $subGrossCertified,
            'sub_net_payable' => $subNetPayable,
            'sub_paid' => $subPaidAmount,
            'sub_outstanding' => $subOutstandingPayable,

            'total_actual_cash_paid' => $totalActualCashPaid,
        ];

        $pendingActions = [
            'pending_prs' => $pendingPRs,
            'draft_invoices' => $clientInvoices->where('status', 'draft'),
            'submitted_invoices' => $clientInvoices->where('status', 'submitted'),
            'draft_sub_bills' => $subBills->where('status', 'draft'),
            'submitted_sub_bills' => $subBills->where('status', 'submitted'),
            'pending_expenses' => $expenses->where('status', 'pending'),
        ];

        return view('projects.show', compact(
            'project',
            'financials',
            'pendingActions',
            'clientInvoices',
            'purchaseOrders',
            'subWorkOrders',
            'subBills',
            'expenses',
            'supplierInvoices'
        ));
    }

    public function edit(Project $project): View
    {
        $this->authorizeManage();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($project->company_id !== $companyId) {
            abort(404, 'Project not found in active company.');
        }

        $clients = Client::where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get();
        $managers = User::whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))->orderBy('name')->get();

        return view('projects.edit', compact('project', 'clients', 'managers'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeManage();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($project->company_id !== $companyId) {
            abort(404, 'Project not found in active company.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'project_type' => 'required|in:civil,interior,maintenance,earthmoving',
            'location' => 'nullable|string|max:255',
            'target_completion_date' => 'nullable|date',
            'contract_value' => 'required|numeric|min:0',
            'approved_budget' => 'required|numeric|min:0',
            'status' => 'required|in:planned,active,on_track,at_risk,delayed,completed,on_hold,cancelled',
            'progress_percent' => 'required|integer|between:0,100',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $oldStatus = $project->status;
        $project->update($validated);

        if ($oldStatus !== $validated['status'] && $project->manager_id) {
            AppNotification::create([
                'company_id' => $project->company_id,
                'user_id' => $project->manager_id,
                'title' => 'Project Status Changed',
                'message' => "Project '{$project->name}' status changed to " . ucfirst(str_replace('_', ' ', $validated['status'])) . ".",
                'type' => in_array($validated['status'], ['at_risk', 'delayed']) ? 'danger' : 'info',
                'action_url' => route('projects.show', $project),
                'is_read' => false,
            ]);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Project updated successfully.');
    }

    /**
     * Authorize viewing projects.
     */
    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('projects.view')
            && ! $user->hasPermission('projects.*')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant']))) {
            abort(403, 'Unauthorized access to Project records.');
        }
    }

    /**
     * Authorize creating/modifying projects.
     */
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('projects.manage')
            && ! $user->hasPermission('projects.*')
            && ! in_array($role, ['owner', 'admin', 'project_manager']))) {
            abort(403, 'Unauthorized to create or modify Project records.');
        }
    }
}
