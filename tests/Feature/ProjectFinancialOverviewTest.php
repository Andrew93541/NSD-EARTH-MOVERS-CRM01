<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorWorkOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectFinancialOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;
    protected User $siteSupervisorUser;
    protected Company $companyA;
    protected Company $companyB;
    protected Client $client;
    protected Project $project;
    protected Supplier $materialSupplier;
    protected Supplier $subcontractor;
    protected ExpenseCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $ownerRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->ownerUser = User::create([
            'name' => 'Narayan Shinde',
            'email' => 'owner@nsdconstruction.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->companyA->users()->attach($this->ownerUser->id, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Site Supervisor (view only for sites, not authorized to modify projects)
        $supervisorRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'daily_logs.*'],
        ]);

        $this->siteSupervisorUser = User::create([
            'name' => 'Ramesh Patil',
            'email' => 'supervisor@nsdconstruction.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->companyA->users()->attach($this->siteSupervisorUser->id, [
            'role_id' => $supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Competitor Infra Pvt Ltd',
            'slug' => 'competitor-infra',
        ]);

        $this->client = Client::create([
            'company_id' => $this->companyA->id,
            'name' => 'Metro Transport Authority',
            'gstin' => '27AAATM9999A1Z2',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->companyA->id,
            'client_id' => $this->client->id,
            'name' => 'Elevated Viaduct Package 04',
            'code' => 'EVP-04',
            'contract_value' => 10000000.00, // ₹1.00 Cr
            'approved_budget' => 8500000.00,  // ₹85.00 L
            'status' => 'on_track',
            'progress_percent' => 40,
            'manager_id' => $this->ownerUser->id,
        ]);

        $this->materialSupplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Shree Cement ReadyMix',
            'vendor_type' => 'supplier',
            'status' => 'active',
        ]);

        $this->subcontractor = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Kailash Earthmovers',
            'vendor_type' => 'subcontractor',
            'status' => 'active',
        ]);

        $this->expenseCategory = ExpenseCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Fuel & Machinery Lubricants',
        ]);
    }

    public function test_project_financial_totals_calculate_accurately_with_known_records(): void
    {
        // 1. Client Invoice 1: Total ₹30,00,000, fully paid
        $inv1 = ClientInvoice::create([
            'company_id' => $this->companyA->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-EVP-01',
            'issue_date' => Carbon::today()->subDays(60)->format('Y-m-d'),
            'due_date' => Carbon::today()->subDays(30)->format('Y-m-d'),
            'total_amount' => 3000000.00,
            'paid_amount' => 3000000.00,
            'status' => 'paid',
            'created_by' => $this->ownerUser->id,
        ]);

        // 2. Client Invoice 2: Total ₹20,00,000, partially paid ₹8,00,000 => Due ₹12,00,000
        $inv2 = ClientInvoice::create([
            'company_id' => $this->companyA->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-EVP-02',
            'issue_date' => Carbon::today()->subDays(15)->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays(15)->format('Y-m-d'),
            'total_amount' => 2000000.00,
            'paid_amount' => 800000.00,
            'status' => 'partially_paid',
            'created_by' => $this->ownerUser->id,
        ]);

        // Expected metrics:
        // Contract Value = ₹1,00,00,000.00
        // Client Bills Raised = ₹50,00,000.00 (30L + 20L)
        // Money Received = ₹38,00,000.00 (30L + 8L)
        // Outstanding Balance = ₹12,00,000.00 (50L - 38L)
        // Unbilled Contract = ₹50,00,000.00 (1 Cr - 50L)

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.show', $this->project));

        $response->assertStatus(200);
        $response->assertSee('10,000,000.00'); // Contract Value
        $response->assertSee('5,000,000.00');  // Bills Raised
        $response->assertSee('3,800,000.00');  // Money Received
        $response->assertSee('1,200,000.00');  // Outstanding Client Balance
    }

    public function test_correct_handling_of_partial_client_payments_and_receivables(): void
    {
        $invoice = ClientInvoice::create([
            'company_id' => $this->companyA->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-PARTIAL-01',
            'issue_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
            'total_amount' => 100000.00,
            'paid_amount' => 40000.00,
            'status' => 'partially_paid',
            'created_by' => $this->ownerUser->id,
        ]);

        $this->assertEquals(60000.00, $invoice->balanceDue());

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.show', $this->project));

        $response->assertStatus(200);
        $response->assertSee('100,000.00'); // Billed
        $response->assertSee('40,000.00');  // Paid
        $response->assertSee('60,000.00');  // Balance Due
    }

    public function test_separation_of_actual_expenses_from_committed_purchasing_costs(): void
    {
        // 1. Direct Site Expense: ₹75,000 approved/paid
        Expense::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'category_id' => $this->expenseCategory->id,
            'expense_number' => 'EXP-2026-001',
            'title' => 'Excavator Hydraulic Oil and Filters',
            'expense_date' => Carbon::today()->format('Y-m-d'),
            'amount' => 75000.00,
            'status' => 'approved',
            'submitted_by' => $this->ownerUser->id,
        ]);

        // 2. Committed PO: ₹5,00,000 issued (NOT yet paid)
        $po = PurchaseOrder::create([
            'company_id' => $this->companyA->id,
            'supplier_id' => $this->materialSupplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-COMMITTED-01',
            'po_date' => Carbon::today()->format('Y-m-d'),
            'total_amount' => 500000.00,
            'status' => 'approved',
            'created_by' => $this->ownerUser->id,
        ]);

        // 3. Supplier Invoice against PO: Invoiced ₹2,00,000, Paid ₹1,50,000 => Due ₹50,000
        SupplierInvoice::create([
            'company_id' => $this->companyA->id,
            'supplier_id' => $this->materialSupplier->id,
            'purchase_order_id' => $po->id,
            'invoice_number' => 'SINV-001',
            'invoice_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
            'subtotal' => 169491.53,
            'tax_amount' => 30508.47,
            'total_amount' => 200000.00,
            'paid_amount' => 150000.00,
            'status' => 'partially_paid',
        ]);

        // Total Actual Cash Paid = Direct Expense (75,000) + Supplier Paid (1,50,000) = ₹2,25,000
        // Committed PO Total = ₹5,00,000 (Shown separately from cash paid)

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.show', $this->project));

        $response->assertStatus(200);
        $response->assertSee('75,000.00');   // Direct expense
        $response->assertSee('500,000.00');  // Committed PO Total
        $response->assertSee('150,000.00');  // Paid to suppliers
        $response->assertSee('225,000.00');  // Total Actual Cash Paid
    }

    public function test_displays_pending_actions_related_to_the_project(): void
    {
        // 1. Pending Material Request (PR)
        PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'pr_number' => 'PR-2026-901',
            'request_date' => Carbon::today()->format('Y-m-d'),
            'status' => 'pending',
            'requested_by' => $this->ownerUser->id,
            'reason' => 'Urgent reinforcement steel needed for Pier 4',
        ]);

        // 2. Draft Client Invoice
        ClientInvoice::create([
            'company_id' => $this->companyA->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-DRAFT-01',
            'issue_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
            'total_amount' => 500000.00,
            'paid_amount' => 0.00,
            'status' => 'draft',
            'created_by' => $this->ownerUser->id,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.show', $this->project));

        $response->assertStatus(200);
        $response->assertSee('Material Request');
        $response->assertSee('Awaiting procurement review');
        $response->assertSee('Client Bill');
    }

    public function test_tenant_isolation_cannot_view_or_edit_project_from_another_company(): void
    {
        $projectCompanyB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Secret Defense Tunnel',
            'code' => 'SDT-99',
            'contract_value' => 20000000.00,
            'approved_budget' => 18000000.00,
            'status' => 'active',
        ]);

        // Attempt viewing from Company A
        $viewResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.show', $projectCompanyB));
        $viewResponse->assertStatus(404);

        // Attempt editing from Company A
        $editResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.edit', $projectCompanyB));
        $editResponse->assertStatus(404);

        // Attempt updating from Company A
        $updateResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('projects.update', $projectCompanyB), [
                'name' => 'Hijacked Project',
                'project_type' => 'civil',
                'contract_value' => 100.00,
                'approved_budget' => 100.00,
                'status' => 'on_track',
                'progress_percent' => 50,
            ]);
        $updateResponse->assertStatus(404);
    }

    public function test_unauthorized_user_cannot_modify_projects_via_direct_http(): void
    {
        // Site supervisor has no projects.manage right
        $responseCreate = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('projects.create'));
        $responseCreate->assertStatus(403);

        $responseStore = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('projects.store'), [
                'name' => 'Unauthorized Project',
                'code' => 'UNAUTH-01',
                'project_type' => 'civil',
                'contract_value' => 1000000.00,
                'approved_budget' => 900000.00,
            ]);
        $responseStore->assertStatus(403);

        $responseUpdate = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('projects.update', $this->project), [
                'name' => 'Unauthorized Edit',
                'project_type' => 'civil',
                'contract_value' => 1000000.00,
                'approved_budget' => 900000.00,
                'status' => 'on_track',
                'progress_percent' => 50,
            ]);
        $responseUpdate->assertStatus(403);
    }
}
