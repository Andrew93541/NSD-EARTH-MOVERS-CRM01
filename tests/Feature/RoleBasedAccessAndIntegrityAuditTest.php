<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Loan;
use App\Models\LoanEmiSchedule;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleBasedAccessAndIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerUser;
    protected User $pmUser;
    protected User $siteSupervisorUser;
    protected User $hrUser;
    protected User $accountantUser;
    protected User $procurementUser;
    protected User $storekeeperUser;
    protected User $companyBUser;

    protected Project $project;
    protected Site $site;
    protected Warehouse $warehouse;
    protected Material $material;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Companies
        $this->companyA = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Apex Infra Developers Pvt Ltd',
            'slug' => 'apex-infra',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        session(['current_company_id' => $this->companyA->id]);

        // 2. Roles
        $ownerRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $pmRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'materials.request', 'attendance.view', 'attendance.muster'],
        ]);

        $siteSupervisorRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'daily_logs.*', 'attendance.record', 'materials.request'],
        ]);

        $hrRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'HR / Payroll Officer',
            'slug' => 'hr_officer',
            'permissions' => ['employees.*', 'attendance.*', 'payroll.*'],
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*', 'accounting.*', 'expenses.*', 'loans.*', 'procurement.invoices'],
        ]);

        $procurementRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Procurement Officer',
            'slug' => 'procurement_officer',
            'permissions' => ['procurement.*', 'materials.*', 'purchase-requests.view', 'purchase-requests.convert'],
        ]);

        $storekeeperRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*', 'procurement.receive', 'materials.view'],
        ]);

        // 3. Test Users for all 7 roles
        $this->ownerUser = User::create(['name' => 'Rajesh Varma', 'email' => 'admin@buildflow.local', 'password' => Hash::make('Password123!')]);
        $this->pmUser = User::create(['name' => 'Amit Sharma', 'email' => 'pm@buildflow.local', 'password' => Hash::make('Password123!')]);
        $this->siteSupervisorUser = User::create(['name' => 'Vikram Malhotra', 'email' => 'site@buildflow.local', 'password' => Hash::make('Password123!')]);
        $this->hrUser = User::create(['name' => 'Kavita Sharma', 'email' => 'hr@buildflow.local', 'password' => Hash::make('Password123!')]);
        $this->accountantUser = User::create(['name' => 'Sneha Roy', 'email' => 'accounts@buildflow.local', 'password' => Hash::make('Password123!')]);
        $this->procurementUser = User::create(['name' => 'Anand Joshi', 'email' => 'procurement@buildflow.local', 'password' => Hash::make('Password123!')]);
        $this->storekeeperUser = User::create(['name' => 'Gopal Das', 'email' => 'storekeeper@buildflow.local', 'password' => Hash::make('Password123!')]);

        $this->companyA->users()->attach($this->ownerUser->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);
        $this->companyA->users()->attach($this->pmUser->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);
        $this->companyA->users()->attach($this->siteSupervisorUser->id, ['role_id' => $siteSupervisorRole->id, 'status' => 'active', 'is_primary' => true]);
        $this->companyA->users()->attach($this->hrUser->id, ['role_id' => $hrRole->id, 'status' => 'active', 'is_primary' => true]);
        $this->companyA->users()->attach($this->accountantUser->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);
        $this->companyA->users()->attach($this->procurementUser->id, ['role_id' => $procurementRole->id, 'status' => 'active', 'is_primary' => true]);
        $this->companyA->users()->attach($this->storekeeperUser->id, ['role_id' => $storekeeperRole->id, 'status' => 'active', 'is_primary' => true]);

        // Company B User
        $this->companyBUser = User::create(['name' => 'External User', 'email' => 'user@apexinfra.local', 'password' => Hash::make('Password123!')]);
        $companyBRole = Role::create(['company_id' => $this->companyB->id, 'name' => 'Company B Admin', 'slug' => 'owner', 'permissions' => ['*']]);
        $this->companyB->users()->attach($this->companyBUser->id, ['role_id' => $companyBRole->id, 'status' => 'active', 'is_primary' => true]);

        // Domain models
        $this->project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Riverside Residency',
            'code' => 'PRJ-2026-001',
            'project_type' => 'civil',
            'contract_value' => 5000000.00,
            'approved_budget' => 4000000.00,
            'status' => 'on_track',
            'manager_id' => $this->pmUser->id,
        ]);

        $this->site = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'name' => 'Tower A Site',
            'location' => 'Whitefield',
            'supervisor_id' => $this->siteSupervisorUser->id,
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->site->id,
            'name' => 'Tower A Site Store',
            'type' => 'site_store',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create(['company_id' => $this->companyA->id, 'name' => 'Structural Steel']);
        $this->material = Material::create([
            'company_id' => $this->companyA->id,
            'category_id' => $category->id,
            'name' => 'TMT Steel 16mm',
            'code' => 'MAT-STEEL-16',
            'unit_of_measure' => 'MT',
            'standard_cost' => 65000.00,
            'reorder_level' => 5.0,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Jindal Steel & Power Ltd',
            'gstin' => '29AAACJ1234F1Z5',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Direct route access control across roles.
     * Verify that unauthorized roles receive HTTP 403 Forbidden on confidential routes.
     */
    public function test_direct_route_authorization_enforced_across_all_roles(): void
    {
        // 1. Site Supervisor cannot view Payroll, Accounting Journals, or Billing
        $this->actingAs($this->siteSupervisorUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->get(route('payroll.index'))->assertStatus(403);
        $this->get(route('accounting.journals'))->assertStatus(403);
        $this->get(route('accounting.trial-balance'))->assertStatus(403);
        $this->get(route('billing.index'))->assertStatus(403);
        $this->get(route('loans.index'))->assertStatus(403);
        $this->get(route('procurement.create'))->assertStatus(403); // cannot create direct PO

        // But Site Supervisor CAN access site logs, muster roll, and material indents
        $this->get(route('sites.index'))->assertStatus(200);
        $this->get(route('attendance.muster'))->assertStatus(200);
        $this->get(route('purchase-requests.index'))->assertStatus(200);

        // 2. Storekeeper cannot view Payroll or Accounting
        $this->actingAs($this->storekeeperUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->get(route('payroll.index'))->assertStatus(403);
        $this->get(route('accounting.journals'))->assertStatus(403);
        $this->get(route('billing.index'))->assertStatus(403);
        // But Storekeeper CAN access Inventory and Stock Transfers
        $this->get(route('inventory.index'))->assertStatus(200);
        $this->get(route('inventory.transfer'))->assertStatus(200);

        // 3. HR Officer can view and manage Payroll, but cannot access Double-Entry Ledger
        $this->actingAs($this->hrUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->get(route('payroll.index'))->assertStatus(200);
        $this->get(route('employees.create'))->assertStatus(200);
        $this->get(route('accounting.journals'))->assertStatus(403);
        $this->get(route('billing.index'))->assertStatus(403);

        // 4. Accountant can access Billing, Accounting, Loans, but cannot modify Procurement Approval Policies
        $this->actingAs($this->accountantUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->get(route('billing.index'))->assertStatus(200);
        $this->get(route('accounting.journals'))->assertStatus(200);
        $this->get(route('loans.index'))->assertStatus(200);
        $this->get(route('procurement.policies.index'))->assertStatus(403);

        // 5. Owner has unrestricted access to all modules
        $this->actingAs($this->ownerUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->get(route('payroll.index'))->assertStatus(200);
        $this->get(route('accounting.journals'))->assertStatus(200);
        $this->get(route('billing.index'))->assertStatus(200);
        $this->get(route('loans.index'))->assertStatus(200);
        $this->get(route('procurement.policies.index'))->assertStatus(200);
    }

    /**
     * Test 2: Disbursed payroll locks historical attendance from silent edits.
     */
    public function test_disbursed_payroll_locks_historical_attendance(): void
    {
        $employee = Employee::create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Ramesh',
            'last_name' => 'Kumar',
            'code' => 'EMP-001',
            'designation' => 'Excavator Operator',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 1200.00,
            'assigned_site_id' => $this->site->id,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'title' => 'October 2026 Payroll',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-15',
            'total_disbursement' => 18000.00,
            'status' => 'disbursed',
        ]);

        // Attempt by site supervisor to edit attendance for 2026-10-05 (covered by disbursed period)
        $this->actingAs($this->siteSupervisorUser);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->post(route('attendance.muster.store'), [
            'date' => '2026-10-05',
            'site_id' => $this->site->id,
            'attendance' => [
                $employee->id => [
                    'status' => 'present',
                    'overtime_hours' => 4.0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('date');
        $this->assertDatabaseMissing('daily_attendance', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-05',
            'overtime_hours' => 4.0,
        ]);
    }

    /**
     * Test 3: Idempotent Loan EMI Payments.
     * Prevents double payment or balance corruption when recording installment payments.
     */
    public function test_loan_emi_payment_idempotency_prevents_double_deduction(): void
    {
        $this->actingAs($this->accountantUser);
        session(['current_company_id' => $this->companyA->id]);

        $loan = Loan::create([
            'company_id' => $this->companyA->id,
            'loan_account_number' => 'LOAN-CAT-320D',
            'lender_name' => 'Tata Capital Equipment Finance',
            'loan_type' => 'equipment_finance',
            'asset_description' => 'CAT 320D Hydraulic Excavator',
            'principal_amount' => 1000000.00,
            'outstanding_principal' => 1000000.00,
            'annual_interest_rate' => 10.5,
            'tenure_months' => 24,
            'monthly_emi' => 46376.00,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $schedule = LoanEmiSchedule::create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => '2026-02-01',
            'principal_component' => 37626.00,
            'interest_component' => 8750.00,
            'total_emi' => 46376.00,
            'remaining_balance' => 962374.00,
            'status' => 'pending',
        ]);

        // First payment: should succeed
        $response1 = $this->post(route('loans.emi.pay', $schedule), [
            'payment_ref' => 'RTGS-HDFC-998811',
            'paid_date' => '2026-02-01',
        ]);
        $response1->assertSessionHas('success');

        $loan->refresh();
        $schedule->refresh();
        $this->assertEquals('paid', $schedule->status);
        $this->assertEquals(962374.00, (float) $loan->outstanding_principal);

        // Second payment attempt on the SAME installment: must be blocked with error
        $response2 = $this->post(route('loans.emi.pay', $schedule), [
            'payment_ref' => 'RTGS-HDFC-998811-DUPLICATE',
            'paid_date' => '2026-02-01',
        ]);
        $response2->assertSessionHasErrors('status');

        // Verify balance was NOT deducted a second time
        $loan->refresh();
        $this->assertEquals(962374.00, (float) $loan->outstanding_principal);
    }

    /**
     * Test 4: Client Invoice Payment Concurrency and Overpayment Protection.
     */
    public function test_client_invoice_payment_rejects_amount_exceeding_balance_due(): void
    {
        $this->actingAs($this->accountantUser);
        session(['current_company_id' => $this->companyA->id]);

        $client = Client::create([
            'company_id' => $this->companyA->id,
            'name' => 'Prestige Estates Projects Ltd',
            'gstin' => '29AAACP9876C1Z4',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
        ]);

        $invoice = ClientInvoice::create([
            'company_id' => $this->companyA->id,
            'client_id' => $client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-2026-001',
            'bill_type' => 'progress_ra',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'subtotal' => 100000.00,
            'cgst_amount' => 9000.00,
            'sgst_amount' => 9000.00,
            'retention_rate_percent' => 5.0,
            'retention_amount' => 5000.00,
            'total_amount' => 113000.00,
            'paid_amount' => 50000.00,
            'status' => 'partially_paid',
            'created_by' => $this->accountantUser->id,
        ]);

        // Balance due is 113,000 - 50,000 = 63,000
        $this->assertEquals(63000.00, $invoice->balanceDue());

        // Attempting to record payment of 70,000 should fail
        $response = $this->post(route('billing.payments.store', $invoice), [
            'payment_date' => '2026-10-15',
            'amount' => 70000.00,
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'NEFT-AXIS-0099',
        ]);
        $response->assertSessionHasErrors('amount');

        // Recording exact valid payment of 63,000 should succeed and mark invoice as paid
        $responseValid = $this->post(route('billing.payments.store', $invoice), [
            'payment_date' => '2026-10-15',
            'amount' => 63000.00,
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'NEFT-AXIS-0100',
        ]);
        $responseValid->assertSessionHas('success');

        $invoice->refresh();
        $this->assertEquals(113000.00, (float) $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.00, $invoice->balanceDue());
    }

    /**
     * Test 5: Direct PO Creation Guard.
     * Non-procurement users cannot bypass approval by posting direct POs.
     */
    public function test_non_procurement_user_cannot_create_direct_purchase_order(): void
    {
        $this->actingAs($this->siteSupervisorUser);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->post(route('procurement.store'), [
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_date' => Carbon::today()->toDateString(),
            'material_id' => $this->material->id,
            'quantity' => 10.0,
            'unit_price' => 65000.00,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('purchase_orders', [
            'project_id' => $this->project->id,
            'supplier_id' => $this->supplier->id,
        ]);
    }

    /**
     * Test 6: Cross-Company Isolation and Tenant Switcher Security.
     */
    public function test_cross_company_isolation_and_workspace_switch_enforcement(): void
    {
        // Explicitly configure multi-company mode for this multi-tenant regression test
        config(['company.single_company_mode' => false]);

        // Company B User trying to view Company A's project
        $this->actingAs($this->companyBUser);
        session(['current_company_id' => $this->companyB->id]);

        // Global scope on Project ensures ModelNotFoundException (HTTP 404)
        $this->get(route('projects.show', $this->project))->assertStatus(404);

        // Company B User trying to illegally switch to Company A workspace: HTTP 403
        $this->post(route('workspace.switch', $this->companyA))->assertStatus(403);

        // Session current_company_id remains unchanged
        $this->assertEquals($this->companyB->id, session('current_company_id'));
    }

    /**
     * Test 7: End-to-End Procurement Flow with Stock & Budget Validation.
     */
    public function test_end_to_end_procurement_workflow_across_roles(): void
    {
        // Step 1: Site Supervisor creates and submits a Purchase Request
        $this->actingAs($this->siteSupervisorUser);
        session(['current_company_id' => $this->companyA->id]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-AUDIT1',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->warehouse->id,
            'request_date' => Carbon::today()->toDateString(),
            'required_by_date' => Carbon::today()->addDays(7)->toDateString(),
            'priority' => 'normal',
            'status' => 'draft',
            'reason' => 'Reinforcement steel for Tower A 14th floor slab',
            'requested_by' => $this->siteSupervisorUser->id,
        ]);

        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->material->id,
            'quantity_requested' => 10.0,
            'quantity_ordered' => 0.0,
            'estimated_rate' => 65000.00,
        ]);

        $this->post(route('purchase-requests.submit', $pr))->assertRedirect();
        $pr->refresh();
        $this->assertEquals('pending', $pr->status);
        $this->assertEquals(650000.00, (float) $pr->estimated_total);

        // Step 2: Project Manager approves Level 1
        $this->actingAs($this->pmUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->post(route('purchase-requests.approve', $pr), [
            'comments' => 'Material verified against slab schedule. Approved.',
        ])->assertRedirect();

        $pr->refresh();
        $this->assertDatabaseHas('purchase_request_approvals', [
            'purchase_request_id' => $pr->id,
            'approver_id' => $this->pmUser->id,
            'action' => 'approved',
        ]);

        // Step 3: Owner gives final approval
        $this->actingAs($this->ownerUser);
        session(['current_company_id' => $this->companyA->id]);

        $this->post(route('purchase-requests.approve', $pr), [
            'comments' => 'Final budget approval confirmed.',
        ])->assertRedirect();

        $pr->refresh();
        $this->assertEquals('approved', $pr->status);

        // Step 4: Procurement Officer converts eligible quantities into Purchase Order
        $this->actingAs($this->procurementUser);
        session(['current_company_id' => $this->companyA->id]);

        $convertResponse = $this->post(route('purchase-requests.convert.store', $pr), [
            'supplier_id' => $this->supplier->id,
            'po_date' => Carbon::today()->toDateString(),
            'delivery_due_date' => Carbon::today()->addDays(5)->toDateString(),
            'items' => [
                [
                    'pr_item_id' => $prItem->id,
                    'quantity_to_order' => 10.0,
                    'unit_price' => 65000.00,
                ],
            ],
        ]);
        $convertResponse->assertRedirect();

        $po = PurchaseOrder::where('purchase_request_id', $pr->id)->firstOrFail();
        $this->assertEquals(650000.00, (float) $po->subtotal);
        $this->assertEquals(767000.00, (float) $po->total_amount); // 18% GST

        // Step 5: Storekeeper inspects and receives goods (GRN)
        $this->actingAs($this->storekeeperUser);
        session(['current_company_id' => $this->companyA->id]);

        $poItem = $po->items->first();
        $grnResponse = $this->post(route('procurement.receive.store', $po), [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => Carbon::today()->toDateString(),
            'delivery_challan_number' => 'DC-JINDAL-8899',
            'vehicle_number' => 'KA-04-E-1234',
            'remarks' => '10 MT steel inspected; standard heat test passed.',
            'items' => [
                [
                    'item_id' => $poItem->id,
                    'quantity_received' => 10.0,
                    'quantity_accepted' => 10.0,
                    'quantity_rejected' => 0.0,
                ],
            ],
        ]);
        $grnResponse->assertRedirect();

        // Verify stock ledger was credited
        $this->assertDatabaseHas('stock_ledger_entries', [
            'warehouse_id' => $this->warehouse->id,
            'material_id' => $this->material->id,
            'quantity' => 10.0,
            'direction' => 'in',
        ]);

        // Step 6: Accountant records Supplier Commercial Invoice
        $this->actingAs($this->accountantUser);
        session(['current_company_id' => $this->companyA->id]);

        $invoiceResponse = $this->post(route('procurement.invoice.store', $po), [
            'invoice_number' => 'INV-JINDAL-99001',
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => 650000.00,
            'tax_amount' => 117000.00,
        ]);
        $invoiceResponse->assertRedirect();

        $this->assertDatabaseHas('supplier_invoices', [
            'purchase_order_id' => $po->id,
            'invoice_number' => 'INV-JINDAL-99001',
            'total_amount' => 767000.00,
        ]);
    }
}
