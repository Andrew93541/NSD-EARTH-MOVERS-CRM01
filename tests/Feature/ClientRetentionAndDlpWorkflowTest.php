<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientCompletionCertificate;
use App\Models\ClientContract;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\RetentionReleaseClaim;
use App\Models\Role;
use App\Models\User;
use App\Services\RetentionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRetentionAndDlpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $otherCompany;
    protected User $admin;
    protected User $accountant;
    protected User $pm;
    protected User $siteSupervisor;
    protected Client $client;
    protected Project $project;
    protected ClientContract $contract;
    protected ClientInvoice $invoice1;
    protected ClientInvoice $invoice2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Primary Company & Roles
        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
        ]);

        $ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*', 'accounting.*', 'retention.*'],
        ]);

        $pmRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'retention.view', 'retention.create'],
        ]);

        $siteSupervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'daily_logs.*'],
        ]);

        // Users
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
        ]);
        $this->admin->companies()->attach($this->company->id, ['role_id' => $ownerRole->id, 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Accountant User',
            'email' => 'accountant@test.local',
            'password' => bcrypt('password'),
        ]);
        $this->accountant->companies()->attach($this->company->id, ['role_id' => $accountantRole->id, 'is_primary' => true]);

        $this->pm = User::create([
            'name' => 'PM User',
            'email' => 'pm@test.local',
            'password' => bcrypt('password'),
        ]);
        $this->pm->companies()->attach($this->company->id, ['role_id' => $pmRole->id, 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Supervisor User',
            'email' => 'supervisor@test.local',
            'password' => bcrypt('password'),
        ]);
        $this->siteSupervisor->companies()->attach($this->company->id, ['role_id' => $siteSupervisorRole->id, 'is_primary' => true]);

        // 2. Other Company (Tenant Isolation)
        $this->otherCompany = Company::create([
            'name' => 'Competitor Infrastructure Ltd',
            'slug' => 'competitor-infra',
        ]);

        // 3. Chart of Accounts
        Account::create([
            'company_id' => $this->company->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_system' => true,
        ]);
        Account::create([
            'company_id' => $this->company->id,
            'code' => '1020',
            'name' => 'Accounts Receivable (Clients)',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_system' => true,
        ]);
        Account::create([
            'company_id' => $this->company->id,
            'code' => '1025',
            'name' => 'Retention Money Withheld by Clients',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_system' => true,
        ]);
        Account::create([
            'company_id' => $this->company->id,
            'code' => '4010',
            'name' => 'Contract Revenue',
            'account_type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'is_system' => true,
        ]);

        // 4. Project & Client
        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Prestige Infra Projects Ltd',
            'gstin' => '29AAACP9920K1ZV',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'code' => 'PRJ-RR02',
            'name' => 'Riverside Residency Phase 2',
            'contract_value' => 50000000.00,
            'approved_budget' => 45000000.00,
        ]);

        // 5. Client Contract with 5% Retention & Staged Release (50% PCC / 50% DLP)
        $this->contract = ClientContract::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'contract_number' => 'CNT-2026-RR01',
            'title' => 'Civil & Structural Main Contract',
            'contract_value' => 50000000.00,
            'retention_applicable' => true,
            'retention_rate_percent' => 5.00,
            'max_retention_cap' => 2500000.00,
            'dlp_duration_months' => 12,
            'dlp_start_date_basis' => 'practical_completion',
            'release_terms_type' => 'staged_50_50',
            'status' => 'active',
            'created_by' => $this->accountant->id,
        ]);

        // 6. Source RA Bills
        $this->invoice1 = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-001',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::now()->subMonths(3),
            'due_date' => Carbon::now()->subMonths(2),
            'subtotal' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'retention_amount' => 100000.00,
            'total_amount' => 2260000.00,
            'paid_amount' => 2260000.00,
            'status' => 'paid',
            'created_by' => $this->accountant->id,
        ]);

        $this->invoice2 = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-002',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::now()->subMonths(2),
            'due_date' => Carbon::now()->subMonths(1),
            'subtotal' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'retention_amount' => 100000.00,
            'total_amount' => 2260000.00,
            'paid_amount' => 2260000.00,
            'status' => 'paid',
            'created_by' => $this->accountant->id,
        ]);

        session(['current_company_id' => $this->company->id]);
    }

    /**
     * Test 1: Total retention withheld is accurately calculated from approved RA bills.
     */
    public function test_retention_calculated_accurately_from_ra_bills(): void
    {
        $summary = RetentionService::getContractRetentionSummary($this->contract);

        $this->assertEquals(200000.00, $summary['total_withheld']);
        $this->assertEquals(0.00, $summary['total_claimed']);
        $this->assertEquals(200000.00, $summary['outstanding_withheld']);
        $this->assertCount(2, $summary['stages']);
        $this->assertEquals(100000.00, $summary['stages'][0]['target_amount']);
        $this->assertEquals(100000.00, $summary['stages'][1]['target_amount']);
    }

    /**
     * Test 2: Ineligible stage cannot be claimed without required completion certificate.
     */
    public function test_ineligible_stage_cannot_be_claimed_without_certificate(): void
    {
        $this->actingAs($this->accountant);

        // Stage 1 requires Practical Completion Certificate (PCC)
        $summary = RetentionService::getContractRetentionSummary($this->contract);
        $this->assertFalse($summary['stages'][0]['is_eligible']);
        $this->assertEquals(0.00, $summary['stages'][0]['remaining_eligible']);

        $response = $this->post(route('retention.claims.store', $this->contract), [
            'stage_key' => 'stage_1_completion',
            'claimed_amount' => 50000.00,
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('retention_release_claims', 0);
    }

    /**
     * Test 3: Verified Practical Completion Certificate unlocks Stage 1 eligibility.
     */
    public function test_verified_completion_certificate_unlocks_stage_1_claim(): void
    {
        $this->actingAs($this->admin);

        // Upload and verify PCC
        $cert = ClientCompletionCertificate::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'project_id' => $this->project->id,
            'certificate_type' => 'practical_completion',
            'certificate_number' => 'PCC-2026-001',
            'issuing_authority' => 'Consulting Engineers Ltd',
            'issue_date' => Carbon::now()->subDays(10),
            'effective_date' => Carbon::now()->subDays(10),
            'status' => 'verified',
            'all_defects_rectified' => true,
            'verified_by' => $this->admin->id,
            'created_by' => $this->accountant->id,
        ]);

        $summary = RetentionService::getContractRetentionSummary($this->contract);
        $this->assertTrue($summary['stages'][0]['is_eligible']);
        $this->assertEquals(100000.00, $summary['stages'][0]['remaining_eligible']);

        // Stage 2 (DLP) should still be ineligible because DLP duration (12 months) has not elapsed
        $this->assertFalse($summary['stages'][1]['is_eligible']);
    }

    /**
     * Test 4: Creating a partial claim leaves the exact remaining eligible balance.
     */
    public function test_partial_claim_leaves_correct_remaining_eligible_balance(): void
    {
        $this->actingAs($this->accountant);

        // Issue verified PCC
        ClientCompletionCertificate::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'project_id' => $this->project->id,
            'certificate_type' => 'practical_completion',
            'certificate_number' => 'PCC-2026-001',
            'issuing_authority' => 'Consulting Engineers Ltd',
            'issue_date' => Carbon::now()->subDays(10),
            'effective_date' => Carbon::now()->subDays(10),
            'status' => 'verified',
            'all_defects_rectified' => true,
            'verified_by' => $this->admin->id,
            'created_by' => $this->accountant->id,
        ]);

        // Submit partial claim of ₹60,000 against Stage 1 (Target ₹100,000)
        $claim = RetentionService::createClaim($this->accountant, $this->contract, [
            'stage_key' => 'stage_1_completion',
            'claimed_amount' => 60000.00,
            'submit_for_review' => true,
        ]);

        $this->assertEquals('submitted', $claim->status);
        $this->assertEquals(60000.00, $claim->claimed_amount);

        // Verify remaining eligible balance is now ₹40,000
        $refreshedSummary = RetentionService::getContractRetentionSummary($this->contract);
        $this->assertEquals(40000.00, $refreshedSummary['stages'][0]['remaining_eligible']);
        $this->assertEquals(60000.00, $refreshedSummary['total_claimed']);
    }

    /**
     * Test 5: Rejection requires a mandatory reason and preserves the audit trail.
     */
    public function test_claim_rejection_requires_mandatory_reason_and_logs_audit(): void
    {
        $this->actingAs($this->accountant);

        ClientCompletionCertificate::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'project_id' => $this->project->id,
            'certificate_type' => 'practical_completion',
            'certificate_number' => 'PCC-2026-001',
            'issuing_authority' => 'Consulting Engineers Ltd',
            'issue_date' => Carbon::now()->subDays(10),
            'effective_date' => Carbon::now()->subDays(10),
            'status' => 'verified',
            'created_by' => $this->accountant->id,
        ]);

        $claim = RetentionService::createClaim($this->accountant, $this->contract, [
            'stage_key' => 'stage_1_completion',
            'claimed_amount' => 50000.00,
            'submit_for_review' => true,
        ]);

        $this->actingAs($this->admin);

        // Reject with empty reason should fail validation
        $response = $this->post(route('retention.claims.reject', $claim), [
            'rejection_reason' => '',
        ]);
        $response->assertSessionHasErrors('rejection_reason');

        // Valid rejection
        $response = $this->post(route('retention.claims.reject', $claim), [
            'rejection_reason' => 'Pending resolution of contractor punch list items.',
        ]);

        $claim->refresh();
        $this->assertEquals('rejected', $claim->status);
        $this->assertEquals('Pending resolution of contractor punch list items.', $claim->rejection_reason);

        // Rejected claim restores eligible balance
        $summary = RetentionService::getContractRetentionSummary($this->contract);
        $this->assertEquals(100000.00, $summary['stages'][0]['remaining_eligible']);
    }

    /**
     * Test 6: Segregation of duties - Requester cannot approve their own claim unless Admin/Owner.
     */
    public function test_segregation_of_duties_prevents_self_approval(): void
    {
        $this->actingAs($this->accountant);

        ClientCompletionCertificate::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'project_id' => $this->project->id,
            'certificate_type' => 'practical_completion',
            'certificate_number' => 'PCC-2026-001',
            'issuing_authority' => 'Consulting Engineers Ltd',
            'issue_date' => Carbon::now()->subDays(10),
            'effective_date' => Carbon::now()->subDays(10),
            'status' => 'verified',
            'created_by' => $this->accountant->id,
        ]);

        // Accountant creates claim
        $claim = RetentionService::createClaim($this->accountant, $this->contract, [
            'stage_key' => 'stage_1_completion',
            'claimed_amount' => 50000.00,
            'submit_for_review' => true,
        ]);

        // Accountant tries to self-approve
        $response = $this->post(route('retention.claims.approve', $claim), [
            'approval_remarks' => 'Self-approving my own claim',
        ]);

        $response->assertSessionHas('error');
        $claim->refresh();
        $this->assertEquals('submitted', $claim->status); // Status unchanged!

        // Admin approves successfully
        $this->actingAs($this->admin);
        $response = $this->post(route('retention.claims.approve', $claim), [
            'approval_remarks' => 'Independent executive sign-off verified.',
        ]);

        $claim->refresh();
        $this->assertEquals('approved', $claim->status);
        $this->assertEquals(50000.00, $claim->approved_amount);
    }

    /**
     * Test 7: Recording payment receipt updates balances and posts balanced double-entry accounting.
     */
    public function test_payment_receipt_updates_balance_and_posts_balanced_accounting(): void
    {
        $this->actingAs($this->accountant);

        ClientCompletionCertificate::create([
            'company_id' => $this->company->id,
            'client_contract_id' => $this->contract->id,
            'project_id' => $this->project->id,
            'certificate_type' => 'practical_completion',
            'certificate_number' => 'PCC-2026-001',
            'issuing_authority' => 'Consulting Engineers Ltd',
            'issue_date' => Carbon::now()->subDays(10),
            'effective_date' => Carbon::now()->subDays(10),
            'status' => 'verified',
            'created_by' => $this->accountant->id,
        ]);

        $claim = RetentionService::createClaim($this->accountant, $this->contract, [
            'stage_key' => 'stage_1_completion',
            'claimed_amount' => 50000.00,
            'submit_for_review' => true,
        ]);

        // Admin approves
        RetentionService::approveClaim($this->admin, $claim, 'Approved for billing');

        // Submit to client
        RetentionService::markClientSubmitted($this->accountant, $claim, 'CLI-INV-RET-901');

        $claim->refresh();
        $this->assertEquals('client_submitted', $claim->status);

        // Verify claim journal entry was created and balances
        $claimJournal = JournalEntry::where('reference_type', 'retention_release_claims')
            ->where('reference_id', $claim->id)
            ->first();
        $this->assertNotNull($claimJournal);
        $this->assertEquals(50000.00, $claimJournal->total_amount);
        $this->assertEquals(50000.00, $claimJournal->lines()->sum('debit'));
        $this->assertEquals(50000.00, $claimJournal->lines()->sum('credit'));

        // Record partial receipt of ₹30,000
        $payment = RetentionService::recordClaimReceipt($this->accountant, $claim, [
            'payment_date' => Carbon::today()->toDateString(),
            'amount' => 30000.00,
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'UTR-HDFC-992019',
        ]);

        $claim->refresh();
        $this->assertEquals('partially_received', $claim->status);
        $this->assertEquals(30000.00, $claim->received_amount);
        $this->assertEquals(20000.00, $claim->balanceDue());

        // Verify receipt journal entry was posted and balances
        $receiptJournal = JournalEntry::where('reference_type', 'retention_claim_payments')
            ->where('reference_id', $payment->id)
            ->first();
        $this->assertNotNull($receiptJournal);
        $this->assertEquals(30000.00, $receiptJournal->total_amount);
        $this->assertEquals(30000.00, $receiptJournal->lines()->sum('debit'));
        $this->assertEquals(30000.00, $receiptJournal->lines()->sum('credit'));

        // Overpayment beyond remaining balance (₹20,000) is rejected
        $this->expectException(\InvalidArgumentException::class);
        RetentionService::recordClaimReceipt($this->accountant, $claim, [
            'payment_date' => Carbon::today()->toDateString(),
            'amount' => 25000.00, // Exceeds 20,000!
        ]);
    }

    /**
     * Test 8: Unauthorized users (Site Supervisor) receive HTTP 403 on retention routes.
     */
    public function test_unauthorized_roles_receive_http_403_on_retention_routes(): void
    {
        $this->actingAs($this->siteSupervisor);

        $response = $this->get(route('retention.index'));
        $response->assertStatus(403);

        $response = $this->get(route('retention.contracts.show', $this->contract));
        $response->assertStatus(403);

        $response = $this->post(route('retention.contracts.store'), [
            'contract_number' => 'HACK-001',
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test 9: Multi-tenant cross-company isolation - cannot view contracts from another company.
     */
    public function test_cross_company_isolation_on_retention_contracts(): void
    {
        // Create contract in other company
        $otherProject = Project::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Project',
            'code' => 'OTH-01',
        ]);

        $otherClient = Client::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Client',
        ]);

        $otherContract = ClientContract::create([
            'company_id' => $this->otherCompany->id,
            'client_id' => $otherClient->id,
            'project_id' => $otherProject->id,
            'contract_number' => 'CNT-OTH-999',
            'title' => 'Other Company Secret Contract',
            'contract_value' => 10000000.00,
            'created_by' => $this->admin->id,
        ]);

        // Attempt access from primary company session
        $this->actingAs($this->admin);

        $response = $this->get(route('retention.contracts.show', $otherContract));
        $response->assertStatus(404); // Eloquent global scope BelongsToCompany hides it!
    }
}
