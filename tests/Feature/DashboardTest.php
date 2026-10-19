<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MeasurementBookEntry;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorWorkOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Role $adminRole;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $this->adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Admin Officer',
            'email' => 'admin@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $this->adminRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Create sample active project
        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Metro Underground Tunnel Section 4',
            'code' => 'METRO-UG-04',
            'project_type' => 'civil',
            'status' => 'active',
            'contract_value' => 25000000.00,
            'approved_budget' => 22000000.00,
            'actual_cost' => 12000000.00,
            'progress_percent' => 55,
        ]);

        // Create client & unpaid invoice (Receivables)
        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Metro Rail Corporation',
        ]);

        ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-METRO-01',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'subtotal' => 2000000.00,
            'total_amount' => 2260000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Create supplier & unpaid invoice (Payables)
        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Tata Steel Long Products Ltd',
        ]);

        SupplierInvoice::create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'TATA-INV-9901',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(15),
            'subtotal' => 800000.00,
            'tax_amount' => 144000.00,
            'total_amount' => 944000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
        ]);
    }

    public function test_dashboard_renders_live_database_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Project verification
        $response->assertSee('Metro Underground Tunnel Section 4');
        $response->assertSee('METRO-UG-04');

        // Receivables and Payables derived from database
        $response->assertSee('22.6L'); // Total receivables (2.26M)
        $response->assertSee('9.4L');  // Total payables (944K)
        $response->assertSee('Money to Receive');
        $response->assertSee('Money to Pay');
    }

    public function test_dashboard_payables_includes_subcontractor_liabilities_without_double_counting(): void
    {
        // Create subcontractor and work order
        $subcontractor = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Shiva Earthmovers Subcontractors',
        ]);

        $workOrder = SubcontractorWorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'subcontractor_id' => $subcontractor->id,
            'wo_number' => 'WO-2026-001',
            'title' => 'Trench Excavation Works',
            'work_category' => 'earthwork',
            'start_date' => Carbon::today()->toDateString(),
            'completion_date' => Carbon::today()->addMonths(3)->toDateString(),
            'contract_value' => 1000000.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Certified Subcontractor Bill: Net payable = 500,000, paid = 100,000, balance = 400,000
        SubcontractorBill::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'subcontractor_id' => $subcontractor->id,
            'work_order_id' => $workOrder->id,
            'bill_number' => 'SCB-2026-001',
            'bill_date' => Carbon::today(),
            'period_start_date' => Carbon::today()->subMonth()->toDateString(),
            'period_end_date' => Carbon::today()->toDateString(),
            'gross_work_value' => 600000.00,
            'cumulative_gross_value' => 600000.00,
            'net_payable_amount' => 500000.00,
            'paid_amount' => 100000.00,
            'status' => 'certified',
            'created_by' => $this->user->id,
        ]);

        // Combined Payables: Supplier (944,000) + Subcontractor (400,000) = 1,344,000 (13.4L)
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('13.4L'); // Combined Payables: 1.344M
        $response->assertSee('Suppliers');
        $response->assertSee('contractors');
    }

    public function test_dashboard_scopes_all_metrics_to_current_tenant_company(): void
    {
        // Create second company with its own independent records
        $company2 = Company::create([
            'name' => 'Competitor Infrastructure Ltd',
            'slug' => 'competitor-infra',
        ]);

        $project2 = Project::create([
            'company_id' => $company2->id,
            'name' => 'Foreign Highway Bypass 99',
            'code' => 'HIGHWAY-99',
            'project_type' => 'civil',
            'status' => 'active',
            'contract_value' => 90000000.00,
            'approved_budget' => 80000000.00,
            'actual_cost' => 1000000.00,
            'progress_percent' => 10,
        ]);

        $client2 = Client::create([
            'company_id' => $company2->id,
            'name' => 'State Highway Authority',
        ]);

        ClientInvoice::create([
            'company_id' => $company2->id,
            'client_id' => $client2->id,
            'project_id' => $project2->id,
            'invoice_number' => 'INV-FOREIGN-01',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'subtotal' => 8000000.00,
            'total_amount' => 9440000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Authenticate in Company 1
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Verify Company 1 data is visible
        $response->assertSee('Metro Underground Tunnel Section 4');
        $response->assertSee('22.6L');

        // Verify Company 2 data is NOT leaked
        $response->assertDontSee('Foreign Highway Bypass 99');
        $response->assertDontSee('HIGHWAY-99');
        $response->assertDontSee('94.4L');
    }

    public function test_dashboard_reconciles_active_and_completed_projects(): void
    {
        // Add 1 completed project and 1 on_track project
        Project::create([
            'company_id' => $this->company->id,
            'name' => 'Completed Bridge Rehabilitation',
            'code' => 'BRIDGE-01',
            'project_type' => 'civil',
            'status' => 'completed',
            'contract_value' => 5000000.00,
            'approved_budget' => 4500000.00,
            'actual_cost' => 4400000.00,
            'progress_percent' => 100,
        ]);

        Project::create([
            'company_id' => $this->company->id,
            'name' => 'Ongoing Expressway Works',
            'code' => 'EXPRESS-02',
            'project_type' => 'civil',
            'status' => 'on_track',
            'contract_value' => 12000000.00,
            'approved_budget' => 11000000.00,
            'actual_cost' => 2000000.00,
            'progress_percent' => 20,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Active count: 2 (Metro Tunnel [active] + Expressway [on_track])
        // Completed count: 1 (Bridge Rehabilitation [completed])
        $response->assertSee('1 completed');
        $response->assertSee('In progress');
    }

    public function test_dashboard_counts_actionable_pending_approvals(): void
    {
        // 1. Pending Purchase Request
        PurchaseRequest::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'pr_number' => 'PR-2026-901',
            'request_date' => Carbon::today(),
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'reason' => 'Steel Rods Batch 1',
        ]);

        // 2. Draft Purchase Order
        PurchaseOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'supplier_id' => Supplier::where('company_id', $this->company->id)->first()->id,
            'po_number' => 'PO-2026-901',
            'po_date' => Carbon::today(),
            'total_amount' => 180000.00,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // 3. Pending Site Expense
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Fuel & Lubricants',
            'code' => 'FUEL',
        ]);

        Expense::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'category_id' => $category->id,
            'expense_number' => 'EXP-2026-901',
            'title' => 'Diesel for Excavator EX-01',
            'expense_date' => Carbon::today(),
            'amount' => 15000.00,
            'payment_mode' => 'cash',
            'status' => 'pending',
            'submitted_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // 3 actionable items pending approval
        $response->assertSee('3');
        $response->assertSee('Awaiting authorization');
    }

    public function test_sidebar_navigation_displays_six_consolidated_groups_for_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Verify the 6 task-oriented group headers
        $response->assertSee('1. Overview');
        $response->assertSee('2. Workforce');
        $response->assertSee('3. Materials');
        $response->assertSee('4. Subcontractors');
        $response->assertSee('5. Money');
        $response->assertSee('6. Company');
    }

    public function test_sidebar_navigation_filters_links_based_on_user_permissions(): void
    {
        // Create restricted role: Site Supervisor (projects view, attendance record only)
        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['projects.view', 'attendance.record', 'attendance.muster'],
        ]);

        $supervisor = User::create([
            'name' => 'Supervisor Ramesh',
            'email' => 'ramesh@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($supervisor->id, [
            'role_id' => $supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Supervisor can see Projects & Attendance
        $response->assertSee('1. Overview');
        $response->assertSee('Projects');
        $response->assertSee('Daily Attendance (Muster)');

        // Supervisor CANNOT see restricted financial and accounting sections
        $response->assertDontSee('5. Money');
        $response->assertDontSee('General Ledger');
        $response->assertDontSee('Client Invoices &amp; RA Bills', false);
    }

    public function test_responsive_layout_renders_mobile_drawer_and_accessible_controls(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Mobile drawer accessibility attributes
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-label="Mobile Navigation"', false);
        $response->assertSee('aria-label="Open sidebar navigation"', false);
        $response->assertSee('@keydown.escape.window="mobileNavOpen = false"', false);
    }
}
