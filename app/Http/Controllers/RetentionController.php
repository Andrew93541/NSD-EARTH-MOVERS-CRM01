<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ClientCompletionCertificate;
use App\Models\ClientContract;
use App\Models\ClientInvoice;
use App\Models\Project;
use App\Models\RetentionReleaseClaim;
use App\Services\RetentionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RetentionController extends Controller
{
    /**
     * Display the Retention Register with metrics and filterable contracts & claims.
     */
    public function index(Request $request): View
    {
        $this->authorizeRetentionView();

        $user = Auth::user();
        $companyId = $user->currentCompany()?->id ?? session('current_company_id');

        $metrics = RetentionService::getCompanyRetentionRegisterSummary($companyId);

        $query = ClientContract::where('company_id', $companyId)
            ->with(['client', 'project', 'invoices', 'certificates', 'retentionClaims']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('contract_number', 'like', "%{$s}%")
                    ->orWhere('title', 'like', "%{$s}%");
            });
        }

        $contracts = $query->latest()->paginate(10)->withQueryString();

        // Process stage analysis for each contract
        $contractsSummaries = [];
        foreach ($contracts as $cnt) {
            $contractsSummaries[$cnt->id] = RetentionService::getContractRetentionSummary($cnt);
        }

        // Active Claims query
        $claimsQuery = RetentionReleaseClaim::where('company_id', $companyId)
            ->with(['client', 'project', 'contract', 'payments']);

        if ($request->filled('claim_status')) {
            $claimsQuery->where('status', $request->claim_status);
        }

        $activeClaims = $claimsQuery->latest()->take(10)->get();

        $projects = Project::where('company_id', $companyId)->orderBy('name')->get();
        $clients = Client::where('company_id', $companyId)->orderBy('name')->get();

        return view('retention.index', compact(
            'metrics',
            'contracts',
            'contractsSummaries',
            'activeClaims',
            'projects',
            'clients'
        ));
    }

    /**
     * Show form to register a new client contract.
     */
    public function createContract(): View
    {
        $this->authorizeRetentionManage();

        $user = Auth::user();
        $companyId = $user->currentCompany()?->id ?? session('current_company_id');

        $projects = Project::where('company_id', $companyId)->orderBy('name')->get();
        $clients = Client::where('company_id', $companyId)->orderBy('name')->get();

        return view('retention.contracts.create', compact('projects', 'clients'));
    }

    /**
     * Store a new client contract with retention terms.
     */
    public function storeContract(Request $request): RedirectResponse
    {
        $this->authorizeRetentionManage();

        $user = Auth::user();
        $companyId = $user->currentCompany()?->id ?? session('current_company_id');

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'client_id' => 'required|exists:clients,id',
            'contract_number' => 'required|string|max:50',
            'title' => 'required|string|max:200',
            'contract_value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'completion_date' => 'nullable|date|after_or_equal:start_date',
            'retention_applicable' => 'nullable|boolean',
            'retention_rate_percent' => 'required|numeric|between:0,25',
            'max_retention_cap' => 'nullable|numeric|min:0',
            'dlp_duration_months' => 'required|integer|between:0,60',
            'dlp_start_date_basis' => 'required|in:practical_completion,handover,final_bill',
            'release_terms_type' => 'required|in:staged_50_50,single_full_release,custom_stages',
            'notes' => 'nullable|string',
        ]);

        $contract = ClientContract::create([
            'company_id' => $companyId,
            'project_id' => $validated['project_id'],
            'client_id' => $validated['client_id'],
            'contract_number' => $validated['contract_number'],
            'title' => $validated['title'],
            'contract_value' => $validated['contract_value'],
            'start_date' => $validated['start_date'] ?? null,
            'completion_date' => $validated['completion_date'] ?? null,
            'retention_applicable' => $request->boolean('retention_applicable', true),
            'retention_rate_percent' => $validated['retention_rate_percent'],
            'max_retention_cap' => $validated['max_retention_cap'] ?? null,
            'dlp_duration_months' => $validated['dlp_duration_months'],
            'dlp_start_date_basis' => $validated['dlp_start_date_basis'],
            'release_terms_type' => $validated['release_terms_type'],
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        // Link any existing invoices for this project & client to the new contract
        ClientInvoice::where('project_id', $contract->project_id)
            ->where('client_id', $contract->client_id)
            ->where('company_id', $companyId)
            ->whereNull('client_contract_id')
            ->update(['client_contract_id' => $contract->id]);

        return redirect()->route('retention.contracts.show', $contract)
            ->with('success', "Contract {$contract->contract_number} registered successfully with configured retention terms.");
    }

    /**
     * Show contract retention detail page with stage eligibility, RA bills, and certificates.
     */
    public function showContract(ClientContract $contract): View
    {
        $this->authorizeRetentionView();

        $contract->loadMissing([
            'client',
            'project',
            'invoices.payments',
            'certificates.verifier',
            'retentionClaims.payments',
            'creator',
        ]);

        $summary = RetentionService::getContractRetentionSummary($contract);

        return view('retention.contracts.show', compact('contract', 'summary'));
    }

    /**
     * Store or upload a completion or DLP certificate.
     */
    public function storeCertificate(Request $request, ClientContract $contract): RedirectResponse
    {
        $this->authorizeRetentionManage();

        $user = Auth::user();

        $validated = $request->validate([
            'certificate_type' => 'required|in:practical_completion,handover_note,dlp_clearance,defect_free_certificate,final_measurement_clearance',
            'certificate_number' => 'required|string|max:100',
            'issuing_authority' => 'required|string|max:150',
            'issue_date' => 'required|date',
            'effective_date' => 'required|date',
            'defect_count_reported' => 'nullable|integer|min:0',
            'defect_count_resolved' => 'nullable|integer|min:0',
            'all_defects_rectified' => 'nullable|boolean',
            'inspection_remarks' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $docPath = null;
        $docOriginal = null;
        if ($request->hasFile('document')) {
            $docOriginal = $request->file('document')->getClientOriginalName();
            $docPath = $request->file('document')->store('certificates/' . $contract->company_id, 'local');
        }

        $allRectified = $request->boolean('all_defects_rectified', true);
        if ($validated['defect_count_reported'] > 0 && $validated['defect_count_resolved'] < $validated['defect_count_reported']) {
            $allRectified = false;
        }

        $cert = ClientCompletionCertificate::create([
            'company_id' => $contract->company_id,
            'client_contract_id' => $contract->id,
            'project_id' => $contract->project_id,
            'certificate_type' => $validated['certificate_type'],
            'certificate_number' => $validated['certificate_number'],
            'issuing_authority' => $validated['issuing_authority'],
            'issue_date' => $validated['issue_date'],
            'effective_date' => $validated['effective_date'],
            'dlp_expiry_date' => Carbon::parse($validated['effective_date'])->addMonths($contract->dlp_duration_months ?? 12),
            'status' => 'verified',
            'defect_count_reported' => $validated['defect_count_reported'] ?? 0,
            'defect_count_resolved' => $validated['defect_count_resolved'] ?? 0,
            'all_defects_rectified' => $allRectified,
            'inspection_remarks' => $validated['inspection_remarks'] ?? null,
            'document_path' => $docPath,
            'document_original_name' => $docOriginal,
            'verified_by' => $user->id,
            'verified_at' => Carbon::now(),
            'created_by' => $user->id,
        ]);

        return redirect()->route('retention.contracts.show', $contract)
            ->with('success', "Certificate {$cert->certificate_number} recorded and verified.");
    }

    /**
     * Show form to prepare a retention release claim.
     */
    public function createClaim(ClientContract $contract, Request $request): View|RedirectResponse
    {
        $this->authorizeRetentionClaim();

        $stageKey = $request->query('stage_key');
        $summary = RetentionService::getContractRetentionSummary($contract);

        $stage = collect($summary['stages'])->firstWhere('stage_key', $stageKey);
        if (! $stage) {
            return redirect()->route('retention.contracts.show', $contract)
                ->with('error', 'Please select an eligible release stage.');
        }

        if (! $stage['is_eligible']) {
            return redirect()->route('retention.contracts.show', $contract)
                ->with('error', "Stage is not yet eligible: " . ($stage['ineligible_reason'] ?? 'Prerequisites not met.'));
        }

        if ($stage['remaining_eligible'] <= 0) {
            return redirect()->route('retention.contracts.show', $contract)
                ->with('error', 'The eligible retention for this stage has already been claimed in full.');
        }

        $contract->loadMissing([
            'client',
            'project',
            'invoices' => function ($q) {
                $q->whereIn('status', ['approved', 'partially_paid', 'paid']);
            },
        ]);

        return view('retention.claims.create', compact('contract', 'stage', 'summary'));
    }

    /**
     * Store and optionally submit a retention release claim.
     */
    public function storeClaim(Request $request, ClientContract $contract): RedirectResponse
    {
        $this->authorizeRetentionClaim();

        $validated = $request->validate([
            'stage_key' => 'required|string',
            'claimed_amount' => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'submit_for_review' => 'nullable|boolean',
        ]);

        try {
            $claim = RetentionService::createClaim(Auth::user(), $contract, $request->all());

            return redirect()->route('retention.claims.show', $claim)
                ->with('success', "Retention Release Claim {$claim->claim_number} created successfully.");
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Show details of a retention release claim.
     */
    public function showClaim(RetentionReleaseClaim $claim): View
    {
        $this->authorizeRetentionView();

        $claim->loadMissing([
            'contract.client',
            'contract.project',
            'client',
            'project',
            'certificate',
            'invoiceItems.invoice',
            'payments.bankAccount',
            'payments.recorder',
            'creator',
            'reviewer',
        ]);

        $user = Auth::user();
        $companyId = $user->currentCompany()?->id ?? session('current_company_id');
        $bankAccounts = BankAccount::where('company_id', $companyId)->where('is_active', true)->get();

        return view('retention.claims.show', compact('claim', 'bankAccounts'));
    }

    /**
     * Submit a draft claim for review.
     */
    public function submitClaim(RetentionReleaseClaim $claim): RedirectResponse
    {
        $this->authorizeRetentionClaim();

        if ($claim->status !== 'draft') {
            return back()->with('error', 'Only draft claims can be submitted for review.');
        }

        $claim->update([
            'status' => 'submitted',
            'submission_date' => Carbon::today(),
        ]);

        return back()->with('success', "Claim {$claim->claim_number} submitted for internal management review.");
    }

    /**
     * Approve an internal retention release claim.
     */
    public function approveClaim(Request $request, RetentionReleaseClaim $claim): RedirectResponse
    {
        $this->authorizeRetentionApprove();

        $request->validate([
            'approval_remarks' => 'nullable|string|max:500',
            'approved_amount' => 'nullable|numeric|min:1|max:' . $claim->claimed_amount,
        ]);

        try {
            $approved = RetentionService::approveClaim(
                Auth::user(),
                $claim,
                $request->input('approval_remarks'),
                $request->filled('approved_amount') ? (float) $request->input('approved_amount') : null
            );

            return back()->with('success', "Retention Release Claim {$approved->claim_number} has been approved.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject an internal retention release claim with mandatory reason.
     */
    public function rejectClaim(Request $request, RetentionReleaseClaim $claim): RedirectResponse
    {
        $this->authorizeRetentionApprove();

        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $rejected = RetentionService::rejectClaim(Auth::user(), $claim, $request->input('rejection_reason'));

            return back()->with('warning', "Retention Release Claim {$rejected->claim_number} has been rejected.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark an approved claim as dispatched / submitted to the client.
     */
    public function markClientSubmitted(Request $request, RetentionReleaseClaim $claim): RedirectResponse
    {
        $this->authorizeRetentionManage();

        $request->validate([
            'client_reference' => 'required|string|max:100',
            'client_submission_date' => 'nullable|date',
        ]);

        try {
            $updated = RetentionService::markClientSubmitted(
                Auth::user(),
                $claim,
                $request->input('client_reference'),
                $request->input('client_submission_date')
            );

            return back()->with('success', "Claim marked as submitted to client with reference {$updated->client_reference}.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Record client payment receipt against a retention claim.
     */
    public function recordReceipt(Request $request, RetentionReleaseClaim $claim): RedirectResponse
    {
        $this->authorizeRetentionReceipt();

        $maxDue = $claim->balanceDue();

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => "required|numeric|min:1|max:{$maxDue}",
            'payment_mode' => 'required|in:bank_transfer,cheque,rtgs_neft,cash',
            'reference_number' => 'nullable|string|max:100',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = RetentionService::recordClaimReceipt(Auth::user(), $claim, $validated);

            return back()->with('success', "Payment receipt {$payment->receipt_number} of ₹" . number_format($payment->amount, 2) . " recorded successfully.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // --- Role-Based Authorization Guards ---

    protected function authorizeRetentionView(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('retention.*')
            && ! $user->hasPermission('retention.view')
            && ! $user->hasPermission('billing.*')
            && ! $user->hasPermission('finance.*')
            && ! $user->hasPermission('projects.*')
            && ! in_array($roleSlug, ['owner', 'admin', 'project_manager', 'accountant', 'hr_officer']))) {
            abort(403, 'Unauthorized: Access to client retention register is restricted.');
        }
    }

    protected function authorizeRetentionManage(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('retention.*')
            && ! $user->hasPermission('retention.manage')
            && ! $user->hasPermission('finance.*')
            && ! in_array($roleSlug, ['owner', 'admin', 'accountant', 'project_manager']))) {
            abort(403, 'Unauthorized: Only authorized commercial managers or accountants may configure contracts and certificates.');
        }
    }

    protected function authorizeRetentionClaim(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('retention.*')
            && ! $user->hasPermission('retention.create')
            && ! $user->hasPermission('finance.*')
            && ! in_array($roleSlug, ['owner', 'admin', 'project_manager', 'accountant']))) {
            abort(403, 'Unauthorized: You lack permission to prepare client retention release claims.');
        }
    }

    protected function authorizeRetentionApprove(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('retention.*')
            && ! $user->hasPermission('retention.approve')
            && ! $user->hasPermission('finance.*')
            && ! in_array($roleSlug, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized: Only authorized finance officers, accountants, or administrators may approve retention release claims.');
        }
    }

    protected function authorizeRetentionReceipt(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('retention.*')
            && ! $user->hasPermission('retention.receipt')
            && ! $user->hasPermission('finance.*')
            && ! $user->hasPermission('billing.*')
            && ! in_array($roleSlug, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized: Only finance officers and accountants may record client retention payments.');
        }
    }
}
