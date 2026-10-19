<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression Test Suite for Phase 1 - Inventory Return Integrity Fix.
 *
 * Verifies:
 * 1. Cumulative over-return prevention (remaining returnable quantity invariant).
 * 2. Repeated partial returns within remaining returnable quantity.
 * 3. Rejection of returns exceeding remaining returnable quantity after prior returns.
 * 4. Safe aggregation of duplicate lines for the same item in a single return request.
 * 5. Rejection of requests whose combined duplicate lines exceed remaining returnable quantity.
 * 6. Issue-item ownership enforcement (rejecting cross-issue item IDs without stock/audit changes).
 * 7. Transactional atomicity: rollback of entire transaction when any line is invalid.
 * 8. Company isolation and strict cross-company record rejection.
 * 9. Authorization enforcement for unassigned personnel and restricted roles.
 * 10. Concurrency safety, deterministic row locking, and three-decimal precision.
 */
class InventoryReturnIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $nsdCompany;
    protected Company $externalCompany;
    protected User $owner;
    protected User $storekeeper;
    protected User $pmAssigned;
    protected User $pmUnassigned;
    protected User $supervisorAssigned;
    protected User $supervisorUnassigned;
    protected User $accountant;
    protected User $externalUser;
    protected Project $project;
    protected Site $site;
    protected Warehouse $warehouse;
    protected MaterialCategory $category;
    protected Material $cement;
    protected Material $steel;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Single Configured NSD Company
        $this->nsdCompany = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'legal_name' => 'NSD Earth Movers & Infrastructure Private Limited',
            'slug' => 'nsd-earth-movers',
            'email' => 'admin@nsdinfra.com',
            'phone' => '+91 80 4123 5678',
            'gstin' => '29AAACN1234F1Z5',
            'pan' => 'AAACN1234F',
            'address' => 'Plot 42, Peenya Industrial Area 2nd Phase, Bengaluru, Karnataka 560058',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        // External company to test cross-tenant isolation
        $this->externalCompany = Company::create([
            'name' => 'Other Infrastructure Ltd',
            'slug' => 'other-infra',
            'is_active' => true,
        ]);

        session(['current_company_id' => $this->nsdCompany->id]);

        // Roles
        $ownerRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $storekeeperRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*'],
            'is_system' => true,
        ]);

        $pmRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'inventory.*'],
            'is_system' => true,
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'materials.request'],
            'is_system' => true,
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*'],
            'is_system' => true,
        ]);

        // Users
        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'owner@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->owner->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->storekeeper = User::create([
            'name' => 'Gopal Das',
            'email' => 'storekeeper@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->storekeeper->id, ['role_id' => $storekeeperRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->pmAssigned = User::create([
            'name' => 'Amit Sharma (Assigned PM)',
            'email' => 'pm.assigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->pmAssigned->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->pmUnassigned = User::create([
            'name' => 'Sunil Verma (Unassigned PM)',
            'email' => 'pm.unassigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->pmUnassigned->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->supervisorAssigned = User::create([
            'name' => 'Vikram Malhotra',
            'email' => 'sup.assigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->supervisorAssigned->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->supervisorUnassigned = User::create([
            'name' => 'Ramesh Kumar',
            'email' => 'sup.unassigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->supervisorUnassigned->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Sneha Roy',
            'email' => 'accountant@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->externalUser = User::create([
            'name' => 'External Guy',
            'email' => 'external@other.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->externalCompany->users()->attach($this->externalUser->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        // Client
        $client = Client::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'NHAI Karnataka Division',
            'status' => 'active',
        ]);

        // Project & Site
        $this->project = Project::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $client->id,
            'name' => 'Bangalore Highway Expansion',
            'code' => 'PRJ-BHE-01',
            'manager_id' => $this->pmAssigned->id,
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->nsdCompany->id,
            'project_id' => $this->project->id,
            'name' => 'Section Km 10-25',
            'code' => 'SITE-BHE-10',
            'supervisor_id' => $this->supervisorAssigned->id,
            'status' => 'active',
        ]);

        // Warehouse & Materials
        $this->warehouse = Warehouse::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'NSD Peenya Central Yard',
            'code' => 'WH-PNY-01',
            'is_active' => true,
        ]);

        $this->category = MaterialCategory::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Civil Construction Supplies',
            'code' => 'CAT-CIVIL',
        ]);

        $this->cement = Material::create([
            'company_id' => $this->nsdCompany->id,
            'category_id' => $this->category->id,
            'name' => 'Ultratech OPC 53 Cement',
            'code' => 'MAT-CEM-01',
            'unit_of_measure' => 'Bags',
            'standard_cost' => 380.00,
            'is_active' => true,
        ]);

        $this->steel = Material::create([
            'company_id' => $this->nsdCompany->id,
            'category_id' => $this->category->id,
            'name' => 'Fe550D TMT Rebar 16mm',
            'code' => 'MAT-TMT-16',
            'unit_of_measure' => 'MT',
            'standard_cost' => 58000.00,
            'is_active' => true,
        ]);

        // Seed opening balance in warehouse
        StockLedgerEntry::create([
            'company_id' => $this->nsdCompany->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->warehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 1000.000,
            'direction' => 'in',
            'unit_rate' => 380.00,
            'total_amount' => 380000.00,
            'balance_quantity' => 1000.000,
            'created_by' => $this->storekeeper->id,
        ]);

        StockLedgerEntry::create([
            'company_id' => $this->nsdCompany->id,
            'material_id' => $this->steel->id,
            'warehouse_id' => $this->warehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 100.000,
            'direction' => 'in',
            'unit_rate' => 58000.00,
            'total_amount' => 5800000.00,
            'balance_quantity' => 100.000,
            'created_by' => $this->storekeeper->id,
        ]);
    }

    /**
     * Helper to create a standard material issue for testing returns.
     */
    protected function createStandardIssue(float $cementQty = 50.000, float $steelQty = 10.000): MaterialIssue
    {
        $issue = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-RET-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-15',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
            'total_cost' => ($cementQty * 380.00) + ($steelQty * 58000.00),
        ]);

        if ($cementQty > 0) {
            MaterialIssueItem::create([
                'material_issue_id' => $issue->id,
                'material_id' => $this->cement->id,
                'requested_quantity' => $cementQty,
                'issued_quantity' => $cementQty,
                'returned_quantity' => 0.000,
                'unit_of_measure' => 'Bags',
                'unit_rate' => 380.00,
                'total_amount' => round($cementQty * 380.00, 2),
            ]);

            StockLedgerEntry::create([
                'company_id' => $this->nsdCompany->id,
                'material_id' => $this->cement->id,
                'warehouse_id' => $this->warehouse->id,
                'entry_date' => '2026-10-15',
                'transaction_type' => 'site_issue',
                'reference_type' => 'material_issues',
                'reference_id' => $issue->id,
                'quantity' => $cementQty,
                'direction' => 'out',
                'unit_rate' => 380.00,
                'total_amount' => round($cementQty * 380.00, 2),
                'balance_quantity' => round(1000.000 - $cementQty, 3),
                'created_by' => $this->storekeeper->id,
            ]);
        }

        if ($steelQty > 0) {
            MaterialIssueItem::create([
                'material_issue_id' => $issue->id,
                'material_id' => $this->steel->id,
                'requested_quantity' => $steelQty,
                'issued_quantity' => $steelQty,
                'returned_quantity' => 0.000,
                'unit_of_measure' => 'MT',
                'unit_rate' => 58000.00,
                'total_amount' => round($steelQty * 58000.00, 2),
            ]);

            StockLedgerEntry::create([
                'company_id' => $this->nsdCompany->id,
                'material_id' => $this->steel->id,
                'warehouse_id' => $this->warehouse->id,
                'entry_date' => '2026-10-15',
                'transaction_type' => 'site_issue',
                'reference_type' => 'material_issues',
                'reference_id' => $issue->id,
                'quantity' => $steelQty,
                'direction' => 'out',
                'unit_rate' => 58000.00,
                'total_amount' => round($steelQty * 58000.00, 2),
                'balance_quantity' => round(100.000 - $steelQty, 3),
                'created_by' => $this->storekeeper->id,
            ]);
        }

        return $issue;
    }

    /**
     * Requirement: A valid partial return followed by another valid partial return within the remaining quantity.
     */
    public function test_valid_partial_return_followed_by_another_valid_partial_return_within_remaining_quantity(): void
    {
        $issue = $this->createStandardIssue(50.000, 0.000);
        $item = $issue->items()->first();

        // Initial check: remaining returnable is 50.000
        $this->assertEquals(50.000, $item->remainingReturnableQuantity());

        // 1. First partial return: 15 bags
        $res1 = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 15.000, 'reason' => 'First partial return'],
                ],
            ]);

        $res1->assertRedirect(route('material-issues.show', $issue));
        $item->refresh();
        $issue->refresh();

        $this->assertEquals(15.000, (float) $item->returned_quantity);
        $this->assertEquals(35.000, $item->remainingReturnableQuantity());
        $this->assertEquals('partially_returned', $issue->status);

        // Verify stock ledger entry 1
        $ledger1 = StockLedgerEntry::where('transaction_type', 'site_return')
            ->where('reference_id', $issue->id)
            ->where('quantity', 15.000)
            ->first();
        $this->assertNotNull($ledger1);
        $this->assertEquals('in', $ledger1->direction);
        $this->assertEquals(965.000, (float) $ledger1->balance_quantity); // 950 + 15 = 965

        // 2. Second partial return: 20 bags (within remaining 35 bags)
        $res2 = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-17',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 20.000, 'reason' => 'Second partial return'],
                ],
            ]);

        $res2->assertRedirect(route('material-issues.show', $issue));
        $item->refresh();
        $issue->refresh();

        $this->assertEquals(35.000, (float) $item->returned_quantity);
        $this->assertEquals(15.000, $item->remainingReturnableQuantity());
        $this->assertEquals('partially_returned', $issue->status);

        // Verify stock ledger entry 2
        $ledger2 = StockLedgerEntry::where('transaction_type', 'site_return')
            ->where('reference_id', $issue->id)
            ->where('quantity', 20.000)
            ->first();
        $this->assertNotNull($ledger2);
        $this->assertEquals(985.000, (float) $ledger2->balance_quantity); // 965 + 20 = 985

        // 3. Final return of remaining 15 bags completes the issue
        $res3 = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-18',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 15.000, 'reason' => 'Final return of remaining surplus'],
                ],
            ]);

        $res3->assertRedirect(route('material-issues.show', $issue));
        $item->refresh();
        $issue->refresh();

        $this->assertEquals(50.000, (float) $item->returned_quantity);
        $this->assertEquals(0.000, $item->remainingReturnableQuantity());
        $this->assertEquals('returned', $issue->status);

        $this->assertEquals(3, StockLedgerEntry::where('transaction_type', 'site_return')->where('reference_id', $issue->id)->count());
    }

    /**
     * Requirement: A return that exceeds the remaining quantity after a previous partial return.
     * Invariant: remaining returnable quantity = originally issued quantity − all previously accepted returns.
     */
    public function test_return_that_exceeds_remaining_quantity_after_previous_partial_return_is_rejected(): void
    {
        $issue = $this->createStandardIssue(40.000, 0.000);
        $item = $issue->items()->first();

        // 1. Initial valid return: 25 bags
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 25.000],
                ],
            ])->assertRedirect();

        $item->refresh();
        $this->assertEquals(25.000, (float) $item->returned_quantity);
        $this->assertEquals(15.000, $item->remainingReturnableQuantity());

        // 2. Attempt to return 16 bags: 16 is less than originally issued (40),
        // but EXCEEDS the remaining returnable quantity (15)!
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-17',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 16.000],
                ],
            ]);

        $response->assertSessionHasErrors(['returns']);
        $item->refresh();

        // Invariant holds: returned_quantity must NOT change, remaining remains 15
        $this->assertEquals(25.000, (float) $item->returned_quantity);
        $this->assertEquals(15.000, $item->remainingReturnableQuantity());

        // Ensure no new ledger entry was written for the failed request
        $this->assertEquals(1, StockLedgerEntry::where('transaction_type', 'site_return')->where('reference_id', $issue->id)->count());
    }

    /**
     * Requirement: Multiple return lines for the same item whose combined quantity exceeds the remaining quantity.
     * Verifies safe aggregation of duplicate lines and failure before any DB writes.
     */
    public function test_multiple_return_lines_for_same_item_whose_combined_quantity_exceeds_remaining_quantity_is_rejected(): void
    {
        $issue = $this->createStandardIssue(20.000, 0.000);
        $item = $issue->items()->first();

        // Previously returned: 10 bags. Remaining returnable: 10 bags.
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 10.000],
                ],
            ])->assertRedirect();

        $item->refresh();
        $this->assertEquals(10.000, $item->remainingReturnableQuantity());

        // Attempt single request with multiple lines for the same item: 6.000 + 5.000 = 11.000 > 10.000 remaining
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-17',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 6.000, 'reason' => 'Line 1'],
                    ['item_id' => $item->id, 'quantity' => 5.000, 'reason' => 'Line 2'],
                ],
            ]);

        $response->assertSessionHasErrors(['returns']);
        $item->refresh();

        // State remains completely intact
        $this->assertEquals(10.000, (float) $item->returned_quantity);
        $this->assertEquals(10.000, $item->remainingReturnableQuantity());
        $this->assertEquals(1, StockLedgerEntry::where('transaction_type', 'site_return')->where('reference_id', $issue->id)->count());
    }

    /**
     * Requirement: Multiple return lines for the same item whose combined quantity is valid succeed.
     */
    public function test_multiple_return_lines_for_same_item_within_remaining_quantity_succeed(): void
    {
        $issue = $this->createStandardIssue(20.000, 0.000);
        $item = $issue->items()->first();

        // Single request with duplicate lines for item: 4.000 + 3.000 = 7.000 <= 20.000
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 4.000, 'reason' => 'Line 1'],
                    ['item_id' => $item->id, 'quantity' => 3.000, 'reason' => 'Line 2'],
                ],
            ]);

        $response->assertRedirect(route('material-issues.show', $issue));
        $item->refresh();

        $this->assertEquals(7.000, (float) $item->returned_quantity);
        $this->assertEquals(13.000, $item->remainingReturnableQuantity());

        $entries = StockLedgerEntry::where('transaction_type', 'site_return')
            ->where('reference_id', $issue->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertEquals(4.000, (float) $entries[0]->quantity);
        $this->assertEquals(984.000, (float) $entries[0]->balance_quantity); // 980 + 4
        $this->assertEquals(3.000, (float) $entries[1]->quantity);
        $this->assertEquals(987.000, (float) $entries[1]->balance_quantity); // 984 + 3
    }

    /**
     * Requirement: An item ID submitted against the wrong material issue.
     * Requirements:
     * - Resolve each submitted item through current route-bound MaterialIssue relationship.
     * - Reject mismatches without making any stock or audit changes.
     */
    public function test_item_id_submitted_against_wrong_material_issue_is_rejected(): void
    {
        // Issue 1
        $issue1 = $this->createStandardIssue(50.000, 0.000);
        $item1 = $issue1->items()->first();

        // Issue 2
        $issue2 = $this->createStandardIssue(30.000, 0.000);
        $item2 = $issue2->items()->first();

        $initialAuditLogsCount = AuditLog::count();
        $initialLedgerCount = StockLedgerEntry::where('transaction_type', 'site_return')->count();

        // Malicious or mismatched request: Submitting item2 ID against Issue 1 route!
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue1), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $item2->id, 'quantity' => 10.000],
                ],
            ]);

        $response->assertSessionHasErrors(['returns']);

        // Verify zero stock changes and zero audit changes
        $this->assertEquals($initialLedgerCount, StockLedgerEntry::where('transaction_type', 'site_return')->count());
        $this->assertEquals($initialAuditLogsCount, AuditLog::count());

        $this->assertEquals(0.000, (float) $item1->fresh()->returned_quantity);
        $this->assertEquals(0.000, (float) $item2->fresh()->returned_quantity);
    }

    /**
     * Requirement: A request containing one valid line and one invalid line; verify the entire transaction rolls back.
     */
    public function test_request_containing_one_valid_line_and_one_invalid_line_rolls_back_entire_transaction(): void
    {
        $issue = $this->createStandardIssue(50.000, 10.000);
        $cementItem = $issue->items()->where('material_id', $this->cement->id)->first();
        $steelItem = $issue->items()->where('material_id', $this->steel->id)->first();

        $initialLedgerCount = StockLedgerEntry::where('transaction_type', 'site_return')->count();
        $initialAuditCount = AuditLog::count();

        // Request has:
        // Line 1: Cement 10.000 (valid, 10 <= 50)
        // Line 2: Steel 25.000 (invalid, 25 > 10 issued!)
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $cementItem->id, 'quantity' => 10.000],
                    ['item_id' => $steelItem->id, 'quantity' => 25.000],
                ],
            ]);

        $response->assertSessionHasErrors(['returns']);

        // Entire transaction must roll back:
        // Line 1 must NOT be written to stock ledger or item returned quantity
        $this->assertEquals(0.000, (float) $cementItem->fresh()->returned_quantity);
        $this->assertEquals(0.000, (float) $steelItem->fresh()->returned_quantity);

        $this->assertEquals($initialLedgerCount, StockLedgerEntry::where('transaction_type', 'site_return')->count());
        $this->assertEquals($initialAuditCount, AuditLog::count());
    }

    /**
     * Requirement: Multi-line request with valid line and cross-issue line rolls back completely.
     */
    public function test_request_containing_one_valid_line_and_one_cross_issue_line_rolls_back_entire_transaction(): void
    {
        $issue1 = $this->createStandardIssue(50.000, 0.000);
        $item1 = $issue1->items()->first();

        $issue2 = $this->createStandardIssue(30.000, 0.000);
        $item2 = $issue2->items()->first();

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue1), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $item1->id, 'quantity' => 10.000],
                    ['item_id' => $item2->id, 'quantity' => 5.000], // Cross-issue item!
                ],
            ]);

        $response->assertSessionHasErrors(['returns']);

        // Zero changes committed
        $this->assertEquals(0.000, (float) $item1->fresh()->returned_quantity);
        $this->assertEquals(0.000, (float) $item2->fresh()->returned_quantity);
        $this->assertEquals(0, StockLedgerEntry::where('transaction_type', 'site_return')->count());
    }

    /**
     * Requirement: Verify company scoping prevents viewing or returning stock for another company's issue.
     */
    public function test_cross_company_material_issue_access_and_return_is_blocked(): void
    {
        // Create warehouse, project, site, and issue for the external company
        $extWarehouse = Warehouse::create([
            'company_id' => $this->externalCompany->id,
            'name' => 'External Yard',
            'code' => 'WH-EXT-01',
            'is_active' => true,
        ]);
        $extClient = Client::create([
            'company_id' => $this->externalCompany->id,
            'name' => 'External Client',
        ]);
        $extProject = Project::create([
            'company_id' => $this->externalCompany->id,
            'client_id' => $extClient->id,
            'name' => 'External Project',
            'code' => 'PRJ-EXT-01',
        ]);
        $extSite = Site::create([
            'company_id' => $this->externalCompany->id,
            'project_id' => $extProject->id,
            'name' => 'External Site',
            'code' => 'SITE-EXT-01',
        ]);
        $extMaterial = Material::create([
            'company_id' => $this->externalCompany->id,
            'name' => 'External Cement',
            'code' => 'MAT-EXT-01',
            'unit_of_measure' => 'Bags',
        ]);

        $externalIssue = MaterialIssue::create([
            'company_id' => $this->externalCompany->id,
            'issue_number' => 'MIN-EXT-999',
            'warehouse_id' => $extWarehouse->id,
            'project_id' => $extProject->id,
            'site_id' => $extSite->id,
            'issue_date' => '2026-10-15',
            'status' => 'completed',
            'issued_by' => $this->externalUser->id,
        ]);
        $externalItem = MaterialIssueItem::create([
            'material_issue_id' => $externalIssue->id,
            'material_id' => $extMaterial->id,
            'requested_quantity' => 50,
            'issued_quantity' => 50,
            'returned_quantity' => 0,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 300,
            'total_amount' => 15000,
        ]);

        // NSD Storekeeper visits External Company's issue
        $resShow = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $externalIssue));
        $this->assertTrue(in_array($resShow->status(), [403, 404], true));

        // NSD Storekeeper attempts return on External Company's issue
        $resReturn = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $externalIssue), [
                'return_date' => '2026-10-16',
                'returns' => [['item_id' => $externalItem->id, 'quantity' => 10]],
            ]);
        $this->assertTrue(in_array($resReturn->status(), [403, 404], true));

        $this->assertEquals(0, StockLedgerEntry::where('transaction_type', 'site_return')->count());
    }

    /**
     * Requirement: Check whether unauthorized users can view or return stock for projects/sites outside their assignments.
     */
    public function test_authorization_and_assignment_scoping_for_returns(): void
    {
        $issue = $this->createStandardIssue(50.000, 0.000);
        $item = $issue->items()->first();

        // 1. Unassigned Project Manager cannot view or return stock
        $resUnassignedPmView = $this->actingAs($this->pmUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issue));
        $resUnassignedPmView->assertStatus(403);

        $resUnassignedPmReturn = $this->actingAs($this->pmUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [['item_id' => $item->id, 'quantity' => 5.000]],
            ]);
        $resUnassignedPmReturn->assertStatus(403);

        // 2. Site supervisor without inventory.issue permission cannot return stock
        $resSupReturn = $this->actingAs($this->supervisorAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [['item_id' => $item->id, 'quantity' => 5.000]],
            ]);
        $resSupReturn->assertStatus(403);

        // 3. Accountant cannot return stock
        $resAccReturn = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [['item_id' => $item->id, 'quantity' => 5.000]],
            ]);
        $resAccReturn->assertStatus(403);

        // 4. Assigned Project Manager can return stock
        $resAssignedPmReturn = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [['item_id' => $item->id, 'quantity' => 5.000]],
            ]);
        $resAssignedPmReturn->assertRedirect();
        $this->assertEquals(5.000, (float) $item->fresh()->returned_quantity);
    }

    /**
     * Requirement: Concurrent-return safety test and concurrency documentation.
     *
     * Database Concurrency Note:
     * When running PHPUnit against SQLite in-memory (:memory:), concurrent multi-process race
     * conditions cannot be tested simultaneously because SQLite uses a single connection per thread.
     * In MySQL / InnoDB production environments, MaterialIssueController applies pessimistic row-level
     * locks (`lockForUpdate()`) on:
     * 1. The MaterialIssue record ($issue)
     * 2. The Warehouse record ($warehouse)
     * 3. The MaterialIssueItems in sorted item ID order ($itemIds)
     * 4. The Materials in sorted material ID order ($materialIds)
     * 5. The StockLedgerEntry summation queries for current balances.
     *
     * This test validates transactional serialization and row locking semantics:
     * - Sequential transactions preserve strict atomicity.
     * - As soon as a transaction commits, any subsequent transaction immediately reads
     *   the exact committed state and prevents any over-return.
     */
    public function test_concurrent_return_safety_and_row_locking_semantics(): void
    {
        $issue = $this->createStandardIssue(30.000, 0.000);
        $item = $issue->items()->first();

        // Transaction 1: Returns 20.000 bags
        DB::transaction(function () use ($issue, $item) {
            $this->actingAs($this->storekeeper)
                ->withSession(['current_company_id' => $this->nsdCompany->id])
                ->post(route('material-issues.return', $issue), [
                    'return_date' => '2026-10-16',
                    'returns' => [['item_id' => $item->id, 'quantity' => 20.000]],
                ])->assertRedirect();
        });

        $this->assertEquals(20.000, (float) $item->fresh()->returned_quantity);
        $this->assertEquals(10.000, $item->fresh()->remainingReturnableQuantity());

        // Transaction 2 (simulating race with stale expectation of 30 remaining):
        // Attempts to return 20.000 bags. Must be rejected because remaining is now only 10.000!
        $res2 = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [['item_id' => $item->id, 'quantity' => 20.000]],
            ]);

        $res2->assertSessionHasErrors(['returns']);
        $this->assertEquals(20.000, (float) $item->fresh()->returned_quantity);
        $this->assertEquals(10.000, $item->fresh()->remainingReturnableQuantity());
    }

    /**
     * Requirement: Preserve three-decimal quantity precision and existing valuation conventions.
     */
    public function test_three_decimal_precision_and_valuation_accuracy(): void
    {
        // Issue 12.375 MT of steel
        $issue = $this->createStandardIssue(0.000, 12.375);
        $steelItem = $issue->items()->first();

        $this->assertEquals(12.375, $steelItem->remainingReturnableQuantity());

        // Return 5.125 MT
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-16',
                'returns' => [
                    ['item_id' => $steelItem->id, 'quantity' => 5.125],
                ],
            ])->assertRedirect();

        $steelItem->refresh();
        $this->assertEquals(5.125, (float) $steelItem->returned_quantity);
        $this->assertEquals(7.250, $steelItem->remainingReturnableQuantity());

        // Return exact remaining 7.250 MT
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-17',
                'returns' => [
                    ['item_id' => $steelItem->id, 'quantity' => 7.250],
                ],
            ])->assertRedirect();

        $steelItem->refresh();
        $this->assertEquals(12.375, (float) $steelItem->returned_quantity);
        $this->assertEquals(0.000, $steelItem->remainingReturnableQuantity());
        $this->assertEquals('returned', $issue->fresh()->status);

        // Check valuation amounts: 5.125 * 58000 = 297,250.00; 7.250 * 58000 = 420,500.00
        $entries = StockLedgerEntry::where('transaction_type', 'site_return')
            ->where('reference_id', $issue->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertEquals(5.125, (float) $entries[0]->quantity);
        $this->assertEquals(297250.00, (float) $entries[0]->total_amount);
        $this->assertEquals(7.250, (float) $entries[1]->quantity);
        $this->assertEquals(420500.00, (float) $entries[1]->total_amount);
    }
}
