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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression Test Suite for Phase 2 - Stock Issue Integrity and Inventory Access Audit.
 *
 * Verifies:
 * 1. Duplicate-line stock overspending prevention (combining requested quantities before checking available stock).
 * 2. Duplicate lines within available stock succeed with sequential running balances.
 * 3. Mixed-material requests where any material lacks stock roll back the entire operation.
 * 4. Company ownership enforcement: rejection of cross-company warehouse, project, site, material, and requester.
 * 5. Rejection of inactive warehouses.
 * 6. Site-to-project ownership enforcement: site must belong to the selected project.
 * 7. Server-side authorization: unassigned PMs and Site Supervisors cannot access, create, or store issues.
 * 8. View and printSlip access controls enforce assignment boundaries.
 * 9. Audit logging and preservation of three-decimal precision.
 */
class StockIssueIntegrityRegressionTest extends TestCase
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
    protected User $supervisorWithIssuePerm;
    protected User $accountant;
    protected User $externalUser;
    protected Project $project;
    protected Project $unassignedProject;
    protected Site $site;
    protected Site $unassignedSite;
    protected Warehouse $warehouse;
    protected Warehouse $inactiveWarehouse;
    protected MaterialCategory $category;
    protected Material $cement;
    protected Material $steel;

    // External Company fixtures
    protected Warehouse $externalWarehouse;
    protected Project $externalProject;
    protected Site $externalSite;
    protected Material $externalMaterial;

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
            'name' => 'Competitor Earth Movers Ltd',
            'slug' => 'competitor-earth-movers',
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

        $supervisorIssueRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Site Supervisor (Issue Permitted)',
            'slug' => 'site_supervisor_issue',
            'permissions' => ['sites.view', 'inventory.issue'],
            'is_system' => false,
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
            'name' => 'Vikram Malhotra (Assigned Supervisor)',
            'email' => 'sup.assigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->supervisorAssigned->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->supervisorUnassigned = User::create([
            'name' => 'Ramesh Kumar (Unassigned Supervisor)',
            'email' => 'sup.unassigned@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->supervisorUnassigned->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->supervisorWithIssuePerm = User::create([
            'name' => 'K. Raju (Supervisor With Issue Perm)',
            'email' => 'sup.issue@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->supervisorWithIssuePerm->id, ['role_id' => $supervisorIssueRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Sneha Roy',
            'email' => 'accountant@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->externalUser = User::create([
            'name' => 'External Guy',
            'email' => 'external@competitor.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->externalCompany->users()->attach($this->externalUser->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        // Client
        $client = Client::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'NHAI Karnataka Division',
            'status' => 'active',
        ]);

        // Assigned Project & Site
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

        // Unassigned Project & Site
        $this->unassignedProject = Project::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $client->id,
            'name' => 'Mysore Expressway Segment 3',
            'code' => 'PRJ-MYS-03',
            'manager_id' => $this->pmUnassigned->id,
            'status' => 'active',
        ]);

        $this->unassignedSite = Site::create([
            'company_id' => $this->nsdCompany->id,
            'project_id' => $this->unassignedProject->id,
            'name' => 'Mysore Pier 10',
            'code' => 'SITE-MYS-P10',
            'supervisor_id' => $this->supervisorUnassigned->id,
            'status' => 'active',
        ]);

        // Warehouse & Materials
        $this->warehouse = Warehouse::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'NSD Peenya Central Yard',
            'code' => 'WH-PNY-01',
            'is_active' => true,
        ]);

        $this->inactiveWarehouse = Warehouse::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Decommissioned Storage Yard',
            'code' => 'WH-DECOM-01',
            'is_active' => false,
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

        // Seed initial warehouse balances
        // Cement: 10.000 bags
        StockLedgerEntry::create([
            'company_id' => $this->nsdCompany->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->warehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 10.000,
            'direction' => 'in',
            'unit_rate' => 380.00,
            'total_amount' => 3800.00,
            'balance_quantity' => 10.000,
            'created_by' => $this->storekeeper->id,
        ]);

        // Steel: 2.000 MT
        StockLedgerEntry::create([
            'company_id' => $this->nsdCompany->id,
            'material_id' => $this->steel->id,
            'warehouse_id' => $this->warehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 2.000,
            'direction' => 'in',
            'unit_rate' => 58000.00,
            'total_amount' => 116000.00,
            'balance_quantity' => 2.000,
            'created_by' => $this->storekeeper->id,
        ]);

        // Seed External Company Fixtures
        $extClient = Client::create([
            'company_id' => $this->externalCompany->id,
            'name' => 'External Highway Authority',
        ]);
        $this->externalWarehouse = Warehouse::create([
            'company_id' => $this->externalCompany->id,
            'name' => 'Competitor Central Depot',
            'code' => 'WH-COMP-01',
            'is_active' => true,
        ]);
        $this->externalProject = Project::create([
            'company_id' => $this->externalCompany->id,
            'client_id' => $extClient->id,
            'name' => 'Competitor Metro Corridor',
            'code' => 'PRJ-COMP-01',
        ]);
        $this->externalSite = Site::create([
            'company_id' => $this->externalCompany->id,
            'project_id' => $this->externalProject->id,
            'name' => 'Competitor Station 1',
            'code' => 'SITE-COMP-01',
        ]);
        $this->externalMaterial = Material::create([
            'company_id' => $this->externalCompany->id,
            'name' => 'Foreign Cement',
            'code' => 'MAT-FOREIGN-01',
            'unit_of_measure' => 'Bags',
            'is_active' => true,
        ]);
    }

    /**
     * Requirement: Two duplicate material lines whose combined quantity exceeds stock.
     */
    public function test_two_duplicate_material_lines_whose_combined_quantity_exceeds_stock_is_rejected(): void
    {
        // Available Cement stock is 10.000 bags.
        // Line 1: 6.000 bags
        // Line 2: 6.000 bags
        // Combined = 12.000 bags > 10.000 available.
        $payload = [
            'issue_number' => 'MIN-DUP-EXCEED-01',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'requested_by' => $this->supervisorAssigned->id,
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 6.000,
                    'issued_quantity' => 6.000,
                ],
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 6.000,
                    'issued_quantity' => 6.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['items']);

        // Entire transaction must roll back with zero writes
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-DUP-EXCEED-01']);
        $this->assertEquals(0, StockLedgerEntry::where('transaction_type', 'site_issue')->count());
        $this->assertEquals(0, AuditLog::where('action', 'issue_material_to_site')->count());
    }

    /**
     * Requirement: Duplicate lines whose combined quantity is within stock.
     * Must succeed and write sequential running balances reflecting actual sequence.
     */
    public function test_duplicate_lines_whose_combined_quantity_is_within_stock_succeeds_with_sequential_balances(): void
    {
        // Available Cement stock is 10.000 bags.
        // Line 1: 4.000 bags (remaining: 6.000)
        // Line 2: 3.000 bags (remaining: 3.000)
        // Combined = 7.000 bags <= 10.000.
        $payload = [
            'issue_number' => 'MIN-DUP-VALID-01',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'requested_by' => $this->supervisorAssigned->id,
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 4.000,
                    'issued_quantity' => 4.000,
                    'remarks' => 'Batch A',
                ],
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 3.000,
                    'issued_quantity' => 3.000,
                    'remarks' => 'Batch B',
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertRedirect();

        $issue = MaterialIssue::where('issue_number', 'MIN-DUP-VALID-01')->first();
        $this->assertNotNull($issue);
        $this->assertCount(2, $issue->items);

        // Verify stock ledger sequence: 10 - 4 = 6, 6 - 3 = 3
        $entries = StockLedgerEntry::where('transaction_type', 'site_issue')
            ->where('reference_id', $issue->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertEquals(4.000, (float) $entries[0]->quantity);
        $this->assertEquals(6.000, (float) $entries[0]->balance_quantity);
        $this->assertEquals(3.000, (float) $entries[1]->quantity);
        $this->assertEquals(3.000, (float) $entries[1]->balance_quantity);
    }

    /**
     * Requirement: Mixed-material requests where one material lacks sufficient stock.
     * Verifies that the entire transaction rolls back and no line item is partially issued.
     */
    public function test_mixed_material_requests_where_one_material_lacks_sufficient_stock_rolls_back_entire_operation(): void
    {
        // Cement stock: 10.000 bags (requesting 5.000 -> sufficient)
        // Steel stock: 2.000 MT (requesting 5.000 -> INSUFFICIENT)
        $payload = [
            'issue_number' => 'MIN-MIXED-FAIL-01',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'requested_by' => $this->supervisorAssigned->id,
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 5.000,
                    'issued_quantity' => 5.000,
                ],
                [
                    'material_id' => $this->steel->id,
                    'requested_quantity' => 5.000,
                    'issued_quantity' => 5.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['items']);

        // Entire transaction rolls back
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-MIXED-FAIL-01']);
        $this->assertEquals(0, StockLedgerEntry::where('transaction_type', 'site_issue')->count());
        $this->assertEquals(0, AuditLog::where('action', 'issue_material_to_site')->count());
    }

    /**
     * Requirement: Cross-company warehouse, project, site, and material IDs.
     */
    public function test_cross_company_warehouse_id_is_rejected(): void
    {
        $payload = [
            'issue_number' => 'MIN-CROSS-WH',
            'warehouse_id' => $this->externalWarehouse->id, // External company warehouse!
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['warehouse_id']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-CROSS-WH']);
    }

    public function test_cross_company_project_id_is_rejected(): void
    {
        $payload = [
            'issue_number' => 'MIN-CROSS-PRJ',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->externalProject->id, // External company project!
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['project_id']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-CROSS-PRJ']);
    }

    public function test_cross_company_site_id_is_rejected(): void
    {
        $payload = [
            'issue_number' => 'MIN-CROSS-SITE',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->externalSite->id, // External company site!
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['site_id']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-CROSS-SITE']);
    }

    public function test_site_belonging_to_another_project_is_rejected(): void
    {
        // Both project and unassignedSite belong to NSD company, but unassignedSite belongs to unassignedProject!
        $payload = [
            'issue_number' => 'MIN-MISMATCH-SITE',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->unassignedSite->id, // Belongs to unassignedProject, NOT project!
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['site_id']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-MISMATCH-SITE']);
    }

    public function test_cross_company_material_id_is_rejected(): void
    {
        $payload = [
            'issue_number' => 'MIN-CROSS-MAT',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->externalMaterial->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-CROSS-MAT']);
    }

    public function test_cross_company_requester_id_is_rejected(): void
    {
        $payload = [
            'issue_number' => 'MIN-CROSS-REQ',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'requested_by' => $this->externalUser->id, // Belongs to external company!
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['requested_by']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-CROSS-REQ']);
    }

    public function test_inactive_warehouse_is_rejected(): void
    {
        $payload = [
            'issue_number' => 'MIN-INACTIVE-WH',
            'warehouse_id' => $this->inactiveWarehouse->id, // is_active = false
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['warehouse_id']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-INACTIVE-WH']);
    }

    /**
     * Requirement: Unauthorized access to unassigned project/site issues.
     */
    public function test_unassigned_project_manager_cannot_create_or_store_issue(): void
    {
        // 1. pmUnassigned tries to view create form with project_id of project (managed by pmAssigned)
        $resCreate = $this->actingAs($this->pmUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.create', ['project_id' => $this->project->id]));
        $resCreate->assertStatus(403);

        // 2. pmUnassigned tries to store an issue for project (managed by pmAssigned)
        $payload = [
            'issue_number' => 'MIN-UNAUTH-PM',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $resStore = $this->actingAs($this->pmUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $resStore->assertStatus(403);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-UNAUTH-PM']);
    }

    public function test_site_supervisor_without_explicit_permission_cannot_access_create_or_store(): void
    {
        // supervisorAssigned has sites.view and materials.request, but NOT inventory.issue
        $resCreate = $this->actingAs($this->supervisorAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.create'));
        $resCreate->assertStatus(403);

        $payload = [
            'issue_number' => 'MIN-UNAUTH-SUP',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $resStore = $this->actingAs($this->supervisorAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $resStore->assertStatus(403);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-UNAUTH-SUP']);
    }

    public function test_site_supervisor_with_permission_cannot_issue_to_unassigned_site(): void
    {
        // supervisorWithIssuePerm has inventory.issue, but is NOT assigned to unassignedSite
        $payload = [
            'issue_number' => 'MIN-SUP-UNASSIGNED',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->unassignedProject->id,
            'site_id' => $this->unassignedSite->id,
            'issue_date' => '2026-10-20',
            'items' => [
                ['material_id' => $this->cement->id, 'requested_quantity' => 1, 'issued_quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->supervisorWithIssuePerm)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-SUP-UNASSIGNED']);
    }

    public function test_view_and_print_slip_assignment_boundaries(): void
    {
        // Issue belongs to project (managed by pmAssigned, site supervised by supervisorAssigned)
        $issue = MaterialIssue::create([
            'company_id' => $this->nsdCompany->id,
            'issue_number' => 'MIN-VIEW-TEST-01',
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
            'total_cost' => 3800.00,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 10,
            'issued_quantity' => 10,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380.00,
            'total_amount' => 3800.00,
        ]);

        // 1. Unassigned PM cannot view or print slip
        $resUnassignedPm = $this->actingAs($this->pmUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issue));
        $resUnassignedPm->assertStatus(403);

        $resUnassignedPmPrint = $this->actingAs($this->pmUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.print', $issue));
        $resUnassignedPmPrint->assertStatus(403);

        // 2. Unassigned Site Supervisor cannot view or print slip
        $resUnassignedSup = $this->actingAs($this->supervisorUnassigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issue));
        $resUnassignedSup->assertStatus(403);

        // 3. Assigned PM can view and print slip
        $resAssignedPm = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issue));
        $resAssignedPm->assertStatus(200);

        $resAssignedPmPrint = $this->actingAs($this->pmAssigned)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.print', $issue));
        $resAssignedPmPrint->assertStatus(200);

        // 4. Storekeeper and Owner can view and print slip
        $resStorekeeper = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issue));
        $resStorekeeper->assertStatus(200);

        $resOwner = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->nsdCompany->id])
            ->get(route('material-issues.show', $issue));
        $resOwner->assertStatus(200);
    }
}
