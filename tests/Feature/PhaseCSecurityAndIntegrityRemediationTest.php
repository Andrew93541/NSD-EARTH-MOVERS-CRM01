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
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseCSecurityAndIntegrityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $nsdCompany;
    protected Company $otherCompany;
    protected User $owner;
    protected User $pmAssigned;
    protected User $pmUnassigned;
    protected User $siteSupervisorAssigned;
    protected User $siteSupervisorUnassigned;
    protected User $storekeeper;
    protected User $accountant;
    protected User $externalUser;
    protected Project $projectA;
    protected Project $projectB;
    protected Site $siteA;
    protected Site $siteB;
    protected Warehouse $warehouse;
    protected Material $cement;
    protected Material $steel;
    protected Client $client;

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

        // 2. Separate external company
        $this->otherCompany = Company::create([
            'name' => 'Competitor Earth Movers Ltd',
            'slug' => 'competitor-earth-movers',
            'is_active' => true,
        ]);

        session(['current_company_id' => $this->nsdCompany->id]);

        // 3. Roles
        $ownerRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $pmRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'materials.request'],
            'is_system' => true,
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'materials.request'],
            'is_system' => true,
        ]);

        $storekeeperRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*'],
            'is_system' => true,
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*', 'quotations.view'],
            'is_system' => true,
        ]);

        // 4. Users
        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'owner@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->owner->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

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

        $this->siteSupervisorAssigned = User::create([
            'name' => 'Vikram Malhotra (Assigned Supervisor)',
            'email' => 'sup.assigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->siteSupervisorAssigned->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisorUnassigned = User::create([
            'name' => 'Ramesh Kumar (Unassigned Supervisor)',
            'email' => 'sup.unassigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->siteSupervisorUnassigned->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->storekeeper = User::create([
            'name' => 'Gopal Das (Storekeeper)',
            'email' => 'storekeeper@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->storekeeper->id, ['role_id' => $storekeeperRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Sneha Roy (Accountant)',
            'email' => 'accountant@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->externalUser = User::create([
            'name' => 'External Guy',
            'email' => 'external@other.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->otherCompany->users()->attach($this->externalUser->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        // 5. Client
        $this->client = Client::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'NHAI Karnataka Division',
            'status' => 'active',
        ]);

        // 6. Projects & Sites
        $this->projectA = Project::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $this->client->id,
            'name' => 'Highway Package 1',
            'code' => 'PRJ-HW-01',
            'manager_id' => $this->pmAssigned->id,
            'contract_value' => 5000000.00,
            'approved_budget' => 4500000.00,
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->nsdCompany->id,
            'project_id' => $this->projectA->id,
            'name' => 'Km 10 to 20 Section',
            'code' => 'SITE-HW-01A',
            'supervisor_id' => $this->siteSupervisorAssigned->id,
            'status' => 'active',
        ]);

        $this->projectB = Project::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $this->client->id,
            'name' => 'Flyover Package 2',
            'code' => 'PRJ-FL-02',
            'manager_id' => $this->pmUnassigned->id,
            'contract_value' => 7000000.00,
            'approved_budget' => 6000000.00,
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->nsdCompany->id,
            'project_id' => $this->projectB->id,
            'name' => 'Pier 1 to 10',
            'code' => 'SITE-FL-02B',
            'supervisor_id' => $this->siteSupervisorUnassigned->id,
            'status' => 'active',
        ]);

        // 7. Warehouse & Materials
        $this->warehouse = Warehouse::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Central Yard',
            'code' => 'WH-CY-01',
            'type' => 'central',
            'is_active' => true,
        ]);

        $cat = MaterialCategory::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Structural',
            'code' => 'CAT-STR',
        ]);

        $this->cement = Material::create([
            'company_id' => $this->nsdCompany->id,
            'category_id' => $cat->id,
            'code' => 'MAT-CEM',
            'name' => 'OPC 53 Cement',
            'unit_of_measure' => 'Bags',
            'standard_cost' => 380.00,
            'is_active' => true,
        ]);

        $this->steel = Material::create([
            'company_id' => $this->nsdCompany->id,
            'category_id' => $cat->id,
            'code' => 'MAT-STL',
            'name' => 'Fe550 Steel',
            'unit_of_measure' => 'MT',
            'standard_cost' => 60000.00,
            'is_active' => true,
        ]);

        // Seed stock: 100 bags cement, 20 MT steel
        StockLedgerEntry::create([
            'company_id' => $this->nsdCompany->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->warehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 100,
            'direction' => 'in',
            'unit_rate' => 380.00,
            'total_amount' => 38000.00,
            'balance_quantity' => 100,
            'created_by' => $this->owner->id,
        ]);

        StockLedgerEntry::create([
            'company_id' => $this->nsdCompany->id,
            'material_id' => $this->steel->id,
            'warehouse_id' => $this->warehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 20,
            'direction' => 'in',
            'unit_rate' => 60000.00,
            'total_amount' => 1200000.00,
            'balance_quantity' => 20,
            'created_by' => $this->owner->id,
        ]);
    }

    // ==========================================
    // 1. INVENTORY ISSUE INTEGRITY
    // ==========================================

    public function test_combined_requested_quantity_across_duplicate_lines_is_validated_against_available_stock(): void
    {
        // Central Yard has 20 MT steel available.
        // User submits 2 lines of 12 MT each (combined 24 MT > 20 MT).
        $payload = [
            'issue_number' => 'MIN-COMBINED-01',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'items' => [
                [
                    'material_id' => $this->steel->id,
                    'requested_quantity' => 12,
                    'issued_quantity' => 12,
                ],
                [
                    'material_id' => $this->steel->id,
                    'requested_quantity' => 12,
                    'issued_quantity' => 12,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['items']);

        // Assert no stock deduction occurred
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-COMBINED-01']);
        $this->assertDatabaseMissing('stock_ledger_entries', ['transaction_type' => 'site_issue']);
    }

    public function test_cannot_issue_stock_with_foreign_or_cross_company_warehouse_or_materials(): void
    {
        // Foreign warehouse belonging to competitor
        $foreignWarehouse = Warehouse::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Foreign Yard',
            'code' => 'WH-FOR-01',
        ]);

        $payload = [
            'issue_number' => 'MIN-CROSS-CO',
            'warehouse_id' => $foreignWarehouse->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 10,
                    'issued_quantity' => 10,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['warehouse_id']);
    }

    // ==========================================
    // 2. INVENTORY RETURN INTEGRITY
    // ==========================================

    public function test_rejects_cross_issue_item_ids_in_return_request(): void
    {
        // Issue 1
        $issue1 = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-ISSUE-1',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
        ]);
        $item1 = MaterialIssueItem::create([
            'material_issue_id' => $issue1->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 20,
            'issued_quantity' => 20,
            'returned_quantity' => 0,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380,
            'total_amount' => 7600,
        ]);

        // Issue 2
        $issue2 = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-ISSUE-2',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
        ]);
        $item2 = MaterialIssueItem::create([
            'material_issue_id' => $issue2->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 30,
            'issued_quantity' => 30,
            'returned_quantity' => 0,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380,
            'total_amount' => 11400,
        ]);

        // Attempt to return item2 against Issue 1!
        $returnPayload = [
            'return_date' => '2026-10-21',
            'returns' => [
                [
                    'item_id' => $item2->id, // Belongs to Issue 2, NOT Issue 1!
                    'quantity' => 10,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue1), $returnPayload);

        $response->assertSessionHasErrors(['returns']);
        $this->assertDatabaseMissing('stock_ledger_entries', ['transaction_type' => 'site_return']);
        $this->assertEquals(0.0, (float) $item2->fresh()->returned_quantity);
    }

    public function test_repeated_partial_returns_and_over_return_prevention(): void
    {
        $issue = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-PARTIAL-RET',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
        ]);

        $item = MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 50,
            'issued_quantity' => 50,
            'returned_quantity' => 0,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380,
            'total_amount' => 19000,
        ]);

        // First partial return: 20 bags
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-21',
                'returns' => [['item_id' => $item->id, 'quantity' => 20]],
            ])->assertRedirect();

        $this->assertEquals(20.0, (float) $item->fresh()->returned_quantity);
        $this->assertEquals('partially_returned', $issue->fresh()->status);

        // Second partial return: 20 bags
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-22',
                'returns' => [['item_id' => $item->id, 'quantity' => 20]],
            ])->assertRedirect();

        $this->assertEquals(40.0, (float) $item->fresh()->returned_quantity);

        // Third return attempt: 15 bags (remaining returnable is only 10!)
        $responseOver = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-23',
                'returns' => [['item_id' => $item->id, 'quantity' => 15]],
            ]);

        $responseOver->assertSessionHasErrors(['returns']);
        // Quantity remains at 40
        $this->assertEquals(40.0, (float) $item->fresh()->returned_quantity);

        // Fourth return: exact remaining 10 bags succeeds and marks status returned
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-23',
                'returns' => [['item_id' => $item->id, 'quantity' => 10]],
            ])->assertRedirect();

        $this->assertEquals(50.0, (float) $item->fresh()->returned_quantity);
        $this->assertEquals('returned', $issue->fresh()->status);
    }

    public function test_duplicate_return_lines_in_single_request_are_aggregated_and_validated(): void
    {
        $issue = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-MULTI-RET-LINE',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
        ]);

        $item = MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 10,
            'issued_quantity' => 10,
            'returned_quantity' => 0,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380,
            'total_amount' => 3800,
        ]);

        // Return request with duplicate lines for item: 6 + 6 = 12 > 10
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issue), [
                'return_date' => '2026-10-21',
                'returns' => [
                    ['item_id' => $item->id, 'quantity' => 6],
                    ['item_id' => $item->id, 'quantity' => 6],
                ],
            ]);

        $response->assertSessionHasErrors(['returns']);
        $this->assertEquals(0.0, (float) $item->fresh()->returned_quantity);
    }

    // ==========================================
    // 3. AUTHORIZATION AND RECORD SCOPE
    // ==========================================

    public function test_project_manager_is_restricted_to_assigned_projects(): void
    {
        // Issue belongs to projectB (managed by pmUnassigned)
        $issueB = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-PRJ-B',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectB->id,
            'site_id' => $this->siteB->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
        ]);

        // pmAssigned (who manages projectA) cannot access issue for projectB
        $response = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issueB));

        $response->assertStatus(403);

        // pmAssigned cannot execute return on projectB
        $responseReturn = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.return', $issueB), [
                'return_date' => '2026-10-21',
                'returns' => [['item_id' => 1, 'quantity' => 1]],
            ]);

        $responseReturn->assertStatus(403);
    }

    public function test_site_supervisor_is_restricted_to_assigned_sites_and_cannot_issue_stock(): void
    {
        // Site supervisor without explicit inventory.issue permission cannot create/post issues
        $responseCreate = $this->actingAs($this->siteSupervisorAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.create'));
        $responseCreate->assertStatus(403);

        $responseStore = $this->actingAs($this->siteSupervisorAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), [
                'issue_number' => 'MIN-ILLEGAL',
                'warehouse_id' => $this->warehouse->id,
                'project_id' => $this->projectA->id,
                'site_id' => $this->siteA->id,
                'issue_date' => '2026-10-20',
                'items' => [['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1]],
            ]);
        $responseStore->assertStatus(403);

        // Cannot view issue belonging to unassigned siteB
        $issueB = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-SITE-B',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->projectB->id,
            'site_id' => $this->siteB->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
        ]);

        $responseView = $this->actingAs($this->siteSupervisorAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issueB));
        $responseView->assertStatus(403);
    }

    public function test_quotation_permissions_strictly_enforced(): void
    {
        // Project manager without explicit quotations permissions cannot create or convert quotations
        $responseCreate = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('quotations.create'));
        $responseCreate->assertStatus(403);

        $quotation = Quotation::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'QT-PERM-TEST',
            'project_title' => 'Bridge Tender',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'accepted',
            'tax_type' => 'gst_18',
            'subtotal' => 100000,
            'total_amount' => 118000,
            'created_by' => $this->owner->id,
        ]);

        // PM cannot change status
        $responseStatus = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->patch(route('quotations.status', $quotation), ['status' => 'sent']);
        $responseStatus->assertStatus(403);

        // PM cannot convert quotation to project without quotations.convert permission
        $responseConvert = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('quotations.convert', $quotation), ['project_name' => 'Bridge PRJ']);
        $responseConvert->assertStatus(403);

        // Owner with '*' can convert quotation to project
        $responseOwnerConvert = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('quotations.convert', $quotation), ['project_name' => 'Bridge PRJ']);
        $responseOwnerConvert->assertRedirect();
        $this->assertNotNull($quotation->fresh()->converted_project_id);
    }

    // ==========================================
    // 4. SINGLE-COMPANY FAIL-CLOSED SAFETY
    // ==========================================

    public function test_single_company_resolution_fails_closed_when_nsd_missing(): void
    {
        // When configured company does not match any DB record
        config([
            'company.default_slug' => 'non-existent-company',
            'company.default_name' => 'Non Existent Company',
        ]);

        // Must return null, and configuredCompanyId must return null (not 1)
        $this->assertNull(Company::configuredCompany());
        $this->assertNull(Company::configuredCompanyId());

        // User currentCompany must return null
        $this->assertNull($this->owner->fresh()->currentCompany());
    }

    public function test_external_company_user_fails_closed_on_nsd_endpoints(): void
    {
        // External user has active membership only in competitor company
        $response = $this->actingAs($this->externalUser)
            ->get(route('dashboard'));

        // Fails closed by redirecting to workspace.select where switching is blocked
        $response->assertRedirect(route('workspace.select'));

        // Direct JSON access returns 403
        $jsonResponse = $this->actingAs($this->externalUser)
            ->getJson(route('dashboard'));
        $jsonResponse->assertStatus(403);
    }

    public function test_suspended_nsd_user_fails_closed(): void
    {
        $this->nsdCompany->users()->updateExistingPivot($this->pmAssigned->id, [
            'status' => 'suspended',
        ]);

        $this->assertNull($this->pmAssigned->fresh()->currentCompany());

        $response = $this->actingAs($this->pmAssigned)
            ->get(route('dashboard'));
        $response->assertRedirect(route('workspace.select'));

        $jsonResponse = $this->actingAs($this->pmAssigned)
            ->getJson(route('dashboard'));
        $jsonResponse->assertStatus(403);
    }
}
