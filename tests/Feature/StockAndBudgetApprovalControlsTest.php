<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProcurementApprovalPolicy;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestApproval;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ApprovalPolicyService;
use App\Services\ProjectBudgetService;
use App\Services\StockAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockAndBudgetApprovalControlsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerUser;
    protected User $pmUser;
    protected User $accountantUser;
    protected User $siteSupervisorUser;
    protected User $procurementUser;
    protected User $companyBUser;
    protected Project $project;
    protected Site $site;
    protected Warehouse $siteStore;
    protected Warehouse $centralYard;
    protected Material $steel;
    protected Material $cement;
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
            'permissions' => ['projects.view', 'materials.request', 'procurement.*'],
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'procurement.*'],
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'materials.request'],
        ]);

        $procurementRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Procurement Officer',
            'slug' => 'procurement_officer',
            'permissions' => ['procurement.*'],
        ]);

        // 3. Users
        $this->ownerUser = User::create([
            'name' => 'Naveen Owner',
            'email' => 'owner@nsdinfra.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyA->users()->attach($this->ownerUser->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->pmUser = User::create([
            'name' => 'Prakash Manager',
            'email' => 'pm@nsdinfra.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyA->users()->attach($this->pmUser->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountantUser = User::create([
            'name' => 'Anand Accountant',
            'email' => 'finance@nsdinfra.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyA->users()->attach($this->accountantUser->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisorUser = User::create([
            'name' => 'Suresh Supervisor',
            'email' => 'supervisor@nsdinfra.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyA->users()->attach($this->siteSupervisorUser->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->procurementUser = User::create([
            'name' => 'Pradeep Procurement',
            'email' => 'procurement@nsdinfra.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyA->users()->attach($this->procurementUser->id, ['role_id' => $procurementRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->companyBUser = User::create([
            'name' => 'Apex User',
            'email' => 'user@apexinfra.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyB->users()->attach($this->companyBUser->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        // 4. Project & Sites
        $this->project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Metro Rail Elevated Viaduct Reach 5',
            'code' => 'PRJ-METRO-05',
            'contract_value' => 50000000.00,
            'approved_budget' => 2000000.00, // ₹20 Lakhs approved budget
            'actual_cost' => 0.00,
            'status' => 'on_track',
            'manager_id' => $this->pmUser->id,
            'start_date' => Carbon::now()->subMonths(3),
            'end_date' => Carbon::now()->addMonths(9),
        ]);

        $this->site = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'name' => 'Pier 42 Casting Yard',
            'code' => 'SITE-P42',
            'status' => 'active',
            'supervisor_id' => $this->siteSupervisorUser->id,
        ]);

        $this->siteStore = Warehouse::create([
            'company_id' => $this->companyA->id,
            'name' => 'Pier 42 Site Store',
            'code' => 'WH-P42',
            'type' => 'site_store',
            'is_active' => true,
        ]);

        $this->centralYard = Warehouse::create([
            'company_id' => $this->companyA->id,
            'name' => 'Peenya Central Stockyard',
            'code' => 'WH-CENTRAL',
            'type' => 'central_warehouse',
            'is_active' => true,
        ]);

        // 5. Materials
        $matCat = MaterialCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Construction Steel',
            'code' => 'CAT-STEEL',
        ]);

        $this->steel = Material::create([
            'company_id' => $this->companyA->id,
            'category_id' => $matCat->id,
            'name' => 'TMT Rebar 16mm Fe550D',
            'code' => 'MAT-TMT-16',
            'unit_of_measure' => 'MT',
            'standard_cost' => 65000.00,
            'is_active' => true,
        ]);

        $this->cement = Material::create([
            'company_id' => $this->companyA->id,
            'category_id' => $matCat->id,
            'name' => 'OPC 53 Grade Cement',
            'code' => 'MAT-OPC-53',
            'unit_of_measure' => 'bags',
            'standard_cost' => 380.00,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Jindal Steel & Power Ltd',
            'code' => 'SUP-JSPL-01',
            'gstin' => '29AAACJ1234F1Z1',
            'status' => 'active',
        ]);
    }

    /**
     * 1. Stock availability is calculated strictly from the StockLedgerEntry for the intended location.
     * Request creation and approval must never deduct or reserve stock.
     */
    public function test_stock_availability_calculated_from_ledger_and_never_deducted_during_request_workflow(): void
    {
        // Initial inventory: 10 MT steel in Central Yard, 2 MT in Site Store
        StockLedgerEntry::create([
            'company_id' => $this->companyA->id,
            'material_id' => $this->steel->id,
            'warehouse_id' => $this->siteStore->id,
            'direction' => 'in',
            'quantity' => 2.000,
            'unit_rate' => 65000.00,
            'total_amount' => 130000.00,
            'transaction_type' => 'opening_balance',
            'reference_type' => 'manual',
            'reference_id' => 1,
            'entry_date' => Carbon::now()->subDays(5),
        ]);

        StockLedgerEntry::create([
            'company_id' => $this->companyA->id,
            'material_id' => $this->steel->id,
            'warehouse_id' => $this->centralYard->id,
            'direction' => 'in',
            'quantity' => 10.000,
            'unit_rate' => 65000.00,
            'total_amount' => 650000.00,
            'transaction_type' => 'goods_receipt',
            'reference_type' => 'grn',
            'reference_id' => 2,
            'entry_date' => Carbon::now()->subDays(3),
        ]);

        // Verify StockAvailabilityService readings
        $this->assertEquals(2.0, StockAvailabilityService::getWarehouseStock($this->steel->id, $this->siteStore->id));
        $this->assertEquals(10.0, StockAvailabilityService::getWarehouseStock($this->steel->id, $this->centralYard->id));
        $this->assertEquals(12.0, StockAvailabilityService::getCompanyTotalStock($this->steel->id, $this->companyA->id));

        // Create a Purchase Request for 5 MT steel at the Site Store
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-STOCK01',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->siteStore->id,
            'request_date' => Carbon::today(),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'Pier 42 cage reinforcement',
            'requested_by' => $this->siteSupervisorUser->id,
            'current_approval_level' => 1,
            'required_approval_levels' => 1,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->steel->id,
            'quantity_requested' => 5.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 65000.00,
        ]);

        $analysis = StockAvailabilityService::analyzeLineItem($item);
        $this->assertEquals(5.0, $analysis['quantity_requested']);
        $this->assertEquals(2.0, $analysis['target_warehouse_stock']);
        $this->assertEquals(3.0, $analysis['shortfall']); // 5 requested - 2 available = 3 shortfall
        $this->assertEquals('partial', $analysis['status']);
        $this->assertTrue($analysis['transfer_available']); // 10 MT available in central yard!
        $this->assertEquals(10.0, $analysis['other_warehouses_stock']);

        // Stock in ledger must remain 2.0 in Site Store (NO physical stock deduction)
        $this->assertEquals(2.0, StockAvailabilityService::getWarehouseStock($this->steel->id, $this->siteStore->id));

        // Now Project Manager approves request
        $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr));

        $this->assertEquals('approved', $pr->fresh()->status);

        // Stock must STILL remain 2.0 MT! (Zero reservation/deduction)
        $this->assertEquals(2.0, StockAvailabilityService::getWarehouseStock($this->steel->id, $this->siteStore->id));
        $this->assertEquals(0, StockLedgerEntry::where('direction', 'out')->count());
    }

    /**
     * 2. Accurate project budget validation:
     * posted actuals (expenses + supplier bills) + commitments (unbilled open POs) without double counting.
     */
    public function test_budget_controls_calculate_actuals_and_commitments_without_double_counting(): void
    {
        // Project approved budget: ₹20,00,000 (20 Lakhs)
        $expCat = ExpenseCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Fuel & Machinery',
            'code' => 'EXP-FUEL',
        ]);

        // Direct posted operational expense: ₹2,00,000
        Expense::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'category_id' => $expCat->id,
            'title' => 'Machinery Fuel & Site Lubricants',
            'expense_number' => 'EXP-2026-001',
            'expense_date' => Carbon::now()->subDays(10),
            'amount' => 200000.00,
            'status' => 'approved',
            'submitted_by' => $this->pmUser->id,
            'recorded_by' => $this->pmUser->id,
        ]);

        // Purchase Order of ₹5,00,000
        $po = PurchaseOrder::create([
            'company_id' => $this->companyA->id,
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-001',
            'po_date' => Carbon::now()->subDays(7),
            'subtotal' => 450000.00,
            'tax_amount' => 50000.00,
            'total_amount' => 500000.00,
            'status' => 'approved',
            'created_by' => $this->procurementUser->id,
        ]);

        // Supplier billed ₹3,00,000 of the ₹5,00,000 PO
        SupplierInvoice::create([
            'company_id' => $this->companyA->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'invoice_number' => 'INV-SUP-001',
            'invoice_date' => Carbon::now()->subDays(4),
            'due_date' => Carbon::now()->addDays(20),
            'subtotal' => 270000.00,
            'tax_amount' => 30000.00,
            'total_amount' => 300000.00,
            'status' => 'approved',
            'received_by' => $this->accountantUser->id,
        ]);

        // Verified Expected Calculations:
        // Actual costs = Expenses (2,00,000) + Invoices (3,00,000) = 5,00,000
        $actualCosts = ProjectBudgetService::getActualCosts($this->project);
        $this->assertEquals(500000.00, $actualCosts);

        // Committed costs = Unbilled remainder of PO (5,00,000 - 3,00,000 = 2,00,000). NOT double counted!
        $committedCosts = ProjectBudgetService::getCommittedCosts($this->project);
        $this->assertEquals(200000.00, $committedCosts);

        // Total committed + actual = 7,00,000
        // Remaining budget before new request = 20,00,000 - 7,00,000 = 13,00,000
        $eval = ProjectBudgetService::evaluateProjectBudget($this->project, 300000.00);
        $this->assertEquals(1300000.00, $eval['remaining_before']);
        $this->assertEquals(1000000.00, $eval['remaining_after']); // 13L - 3L = 10L
        $this->assertFalse($eval['is_over_budget']);
        $this->assertEquals('healthy', $eval['status']);
    }

    /**
     * 3. Over-budget request detection, warnings, and mandatory override reason enforcement.
     */
    public function test_over_budget_request_flags_overrun_and_mandates_override_reason(): void
    {
        // Project approved budget: ₹20,00,000
        // Post expense of ₹18,00,000 (Remaining = ₹2,00,000)
        Expense::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'category_id' => ExpenseCategory::create([
                'company_id' => $this->companyA->id,
                'name' => 'Earthmoving',
                'code' => 'EXP-EARTH',
            ])->id,
            'title' => 'Excavation Contractor Advance',
            'expense_number' => 'EXP-2026-BIG',
            'expense_date' => Carbon::now()->subDays(2),
            'amount' => 1800000.00,
            'status' => 'approved',
            'submitted_by' => $this->pmUser->id,
            'recorded_by' => $this->pmUser->id,
        ]);

        // Create purchase request for ₹5,00,000 (exceeds remaining ₹2,00,000 by ₹3,00,000!)
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-OVER01',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->siteStore->id,
            'request_date' => Carbon::today(),
            'status' => 'pending',
            'priority' => 'critical',
            'reason' => 'Emergency foundation piling',
            'requested_by' => $this->siteSupervisorUser->id,
            'current_approval_level' => 1,
            'required_approval_levels' => 1,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->steel->id,
            'quantity_requested' => 10.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 50000.00, // Total = 5,00,000
        ]);

        $pr->recalculateEstimatedTotalAndBudgetImpact();
        $pr->refresh();

        $this->assertTrue($pr->is_over_budget);
        $this->assertEquals(300000.00, (float) $pr->budget_overrun_amount);

        // Project Manager tries to approve without override reason -> rejected with error
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr), [
                'comments' => 'Approving anyway',
                'is_budget_override' => false,
                'override_reason' => '',
            ]);

        $response->assertSessionHasErrors(['approval']);
        $this->assertEquals('pending', $pr->fresh()->status);

        // Company Owner approves with mandatory override reason
        $overrideResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr), [
                'comments' => 'Owner emergency sign-off',
                'is_budget_override' => true,
                'override_reason' => 'Authorized due to monsoon structural safety deadline approved in board memo 42.',
            ]);

        $overrideResponse->assertRedirect(route('purchase-requests.show', $pr));
        $pr->refresh();

        $this->assertEquals('approved', $pr->status);
        $this->assertTrue($pr->budget_override_approved);
        $this->assertEquals('Authorized due to monsoon structural safety deadline approved in board memo 42.', $pr->budget_override_reason);

        // Verify audit record in purchase_request_approvals
        $this->assertDatabaseHas('purchase_request_approvals', [
            'purchase_request_id' => $pr->id,
            'approver_id' => $this->ownerUser->id,
            'action' => 'approved',
            'is_budget_override' => true,
        ]);
    }

    /**
     * 4. Configurable approval limits & multi-level tiers:
     * Level 1 (PM up to ₹50k), Level 2 (Finance/Accountant ₹50k - ₹5L), Level 3 (Owner > ₹5L).
     */
    public function test_multi_level_approvals_enforced_server_side(): void
    {
        $pmRole = Role::where('slug', 'project_manager')->where('company_id', $this->companyA->id)->first();
        $accountantRole = Role::where('slug', 'accountant')->where('company_id', $this->companyA->id)->first();
        $ownerRole = Role::where('slug', 'owner')->where('company_id', $this->companyA->id)->first();

        // Configure 2 policy tiers for company A
        // Tier 1: Up to ₹50,000 (PM)
        ProcurementApprovalPolicy::create([
            'company_id' => $this->companyA->id,
            'name' => 'Tier 1: Up to 50k',
            'approval_level' => 1,
            'min_amount' => 0.00,
            'max_amount' => 50000.00,
            'approver_role_id' => $pmRole->id,
            'approver_role_slug' => 'project_manager',
            'requires_owner' => false,
            'over_budget_requires_owner' => true,
            'unknown_price_rule' => 'escalate_to_owner',
            'prevent_self_approval' => true,
            'is_active' => true,
        ]);

        // Tier 2: > ₹50,000 (Finance / Accountant)
        ProcurementApprovalPolicy::create([
            'company_id' => $this->companyA->id,
            'name' => 'Tier 2: Above 50k',
            'approval_level' => 2,
            'min_amount' => 50000.00,
            'max_amount' => 1000000.00,
            'approver_role_id' => $accountantRole->id,
            'approver_role_slug' => 'accountant',
            'requires_owner' => false,
            'over_budget_requires_owner' => true,
            'unknown_price_rule' => 'escalate_to_owner',
            'prevent_self_approval' => true,
            'is_active' => true,
        ]);

        // Create PR for ₹1,30,000 (Requires 2 approval levels: PM -> Finance)
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-MULTI01',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->siteStore->id,
            'request_date' => Carbon::today(),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'Structural steel procurement',
            'requested_by' => $this->siteSupervisorUser->id,
            'current_approval_level' => 1,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->steel->id,
            'quantity_requested' => 2.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 65000.00, // Total = ₹1,30,000
        ]);

        $pr->recalculateEstimatedTotalAndBudgetImpact();
        $pr->refresh();

        $this->assertEquals(2, $pr->required_approval_levels);
        $this->assertEquals(1, $pr->current_approval_level);

        // Step 1: Accountant tries to approve Level 1 prematurely -> Blocked
        $prematureAccountant = $this->actingAs($this->accountantUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr));
        $prematureAccountant->assertForbidden();

        // Step 2: Project Manager approves Level 1
        $pmApproval = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr), [
                'comments' => 'PM approved, escalating to Finance',
            ]);

        $pmApproval->assertRedirect(route('purchase-requests.show', $pr));
        $pr->refresh();

        // Status is still pending, but current level is now 2!
        $this->assertEquals('pending', $pr->status);
        $this->assertEquals(2, $pr->current_approval_level);

        // Step 3: Project Manager cannot approve Level 2
        $pmSecondTry = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr));
        $pmSecondTry->assertForbidden();

        // Step 4: Accountant approves Level 2 (Final Level)
        $accountantApproval = $this->actingAs($this->accountantUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr), [
                'comments' => 'Finance approved within cashflow limits',
            ]);

        $accountantApproval->assertRedirect(route('purchase-requests.show', $pr));
        $pr->refresh();

        $this->assertEquals('approved', $pr->status);
        $this->assertEquals(2, $pr->approvals()->count());
    }

    /**
     * 5. Policy management permissions:
     * Non-admins cannot create, update, or delete approval policies (HTTP 403).
     */
    public function test_non_admins_cannot_modify_approval_policies(): void
    {
        $policy = ProcurementApprovalPolicy::create([
            'company_id' => $this->companyA->id,
            'name' => 'Default Tier',
            'approval_level' => 1,
            'min_amount' => 0.00,
            'max_amount' => 100000.00,
            'unknown_price_rule' => 'escalate_to_owner',
            'is_active' => true,
        ]);

        // Site Supervisor tries to view policy configuration -> 403
        $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('procurement.policies.index'))
            ->assertForbidden();

        // Project Manager tries to delete policy -> 403
        $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('procurement.policies.destroy', $policy))
            ->assertForbidden();

        // Company Owner CAN manage policies
        $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('procurement.policies.index'))
            ->assertOk();
    }

    /**
     * 6. PO conversion safeguards:
     * Conversion blocked when required approval tiers are incomplete or over-budget without override.
     */
    public function test_purchase_order_conversion_blocked_if_approvals_or_overrides_missing(): void
    {
        // Unapproved PR cannot be converted
        $pendingPr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-UNAPP',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->siteStore->id,
            'request_date' => Carbon::today(),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'Pending indent',
            'requested_by' => $this->siteSupervisorUser->id,
            'current_approval_level' => 1,
            'required_approval_levels' => 2,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pendingPr->id,
            'material_id' => $this->steel->id,
            'quantity_requested' => 1.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 65000.00,
        ]);

        // Attempting to visit conversion screen is blocked
        $this->actingAs($this->procurementUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('purchase-requests.convert', $pendingPr))
            ->assertRedirect(route('purchase-requests.show', $pendingPr));

        // Attempting to POST conversion is blocked
        $postRes = $this->actingAs($this->procurementUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pendingPr), [
                'supplier_id' => $this->supplier->id,
                'po_date' => Carbon::today()->toDateString(),
                'items' => [
                    [
                        'pr_item_id' => $item->id,
                        'quantity_to_order' => 1.0,
                        'unit_price' => 65000.00,
                    ],
                ],
            ]);

        $postRes->assertRedirect(route('purchase-requests.show', $pendingPr));
        $this->assertEquals(0, PurchaseOrder::where('purchase_request_id', $pendingPr->id)->count());
    }

    /**
     * 7. Cross-company tenant isolation:
     * Company B cannot view or approve Company A's purchase requests or policies.
     */
    public function test_cross_company_isolation_enforced_on_approval_controls(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-ISOLATE',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->siteStore->id,
            'request_date' => Carbon::today(),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'Tenant isolation check',
            'requested_by' => $this->siteSupervisorUser->id,
            'current_approval_level' => 1,
            'required_approval_levels' => 1,
        ]);

        // Company B user attempts to approve Company A request -> 404 / 403
        $response = $this->actingAs($this->companyBUser)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->post(route('purchase-requests.approve', $pr));

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));
    }
}
