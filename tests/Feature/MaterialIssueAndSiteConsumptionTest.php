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

class MaterialIssueAndSiteConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $owner;
    protected User $storekeeper;
    protected User $projectManager;
    protected User $siteSupervisor;
    protected Project $project;
    protected Site $siteA;
    protected Project $projectB;
    protected Site $siteB;
    protected Warehouse $centralWarehouse;
    protected MaterialCategory $category;
    protected Material $cement;
    protected Material $steel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
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

        session(['current_company_id' => $this->company->id]);

        $ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $storekeeperRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*'],
            'is_system' => true,
        ]);

        $pmRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'inventory.*'],
            'is_system' => true,
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'materials.request'],
            'is_system' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'owner@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->owner->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->storekeeper = User::create([
            'name' => 'Gopal Storekeeper',
            'email' => 'store@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->storekeeper->id, ['role_id' => $storekeeperRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->projectManager = User::create([
            'name' => 'Amit Sharma',
            'email' => 'pm@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->projectManager->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Vikram Malhotra',
            'email' => 'supervisor@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->siteSupervisor->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Karnataka Road Development Corp',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'State Highway 17 Widening',
            'code' => 'PRJ-SH17-01',
            'contract_value' => 5000000.00,
            'approved_budget' => 4200000.00,
            'status' => 'active',
            'start_date' => '2026-10-01',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Chainage 12+500 to 18+000 (North Section)',
            'code' => 'SITE-SH17-N',
            'status' => 'active',
        ]);

        $this->projectB = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'Ring Road Flyover Package',
            'code' => 'PRJ-RR-02',
            'contract_value' => 8000000.00,
            'approved_budget' => 6800000.00,
            'status' => 'active',
            'start_date' => '2026-10-01',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->projectB->id,
            'name' => 'Pier 12 to 18 Section',
            'code' => 'SITE-RR-P12',
            'status' => 'active',
        ]);

        $this->centralWarehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'NSD Peenya Central Yard',
            'code' => 'WH-PEENYA-01',
            'type' => 'central',
            'address' => 'Plot 42, Peenya 2nd Phase',
            'is_active' => true,
        ]);

        $this->category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Structural & Bulk Materials',
            'code' => 'CAT-STRUCT',
        ]);

        $this->cement = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'code' => 'MAT-CEM-OPC53',
            'name' => 'Ultratech OPC 53 Grade Cement',
            'unit_of_measure' => 'Bags',
            'standard_cost' => 380.00,
            'reorder_level' => 100,
            'is_active' => true,
        ]);

        $this->steel = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'code' => 'MAT-STL-Fe550',
            'name' => 'Tata Tiscon 16mm TMT Steel',
            'unit_of_measure' => 'MT',
            'standard_cost' => 62000.00,
            'reorder_level' => 20,
            'is_active' => true,
        ]);

        // Seed initial stock: 500 bags cement, 40 MT steel into Central Yard
        StockLedgerEntry::create([
            'company_id' => $this->company->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 500,
            'direction' => 'in',
            'unit_rate' => 380.00,
            'total_amount' => 190000.00,
            'balance_quantity' => 500,
            'created_by' => $this->owner->id,
        ]);

        StockLedgerEntry::create([
            'company_id' => $this->company->id,
            'material_id' => $this->steel->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'opening_balance',
            'quantity' => 40,
            'direction' => 'in',
            'unit_rate' => 62000.00,
            'total_amount' => 2480000.00,
            'balance_quantity' => 40,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_storekeeper_can_issue_materials_to_site_reducing_stock_in_ledger(): void
    {
        $payload = [
            'issue_number' => 'MIN-2026-10-001',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'purpose' => 'Culvert concrete foundation pour',
            'requested_by' => $this->siteSupervisor->id,
            'received_by_name' => 'M. Suresh (Site Foreman)',
            'vehicle_number' => 'KA-04-E-4512 (Tipper)',
            'remarks' => 'Dispatched for North Section chainage 14+200',
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 100,
                    'issued_quantity' => 100,
                    'remarks' => '100 bags batch #2610',
                ],
                [
                    'material_id' => $this->steel->id,
                    'requested_quantity' => 5,
                    'issued_quantity' => 5,
                    'remarks' => '5 MT cut and bent',
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertRedirect();

        $issue = MaterialIssue::where('issue_number', 'MIN-2026-10-001')->first();
        $this->assertNotNull($issue);
        $this->assertEquals($this->company->id, $issue->company_id);
        $this->assertEquals($this->project->id, $issue->project_id);
        $this->assertEquals($this->siteA->id, $issue->site_id);
        $this->assertEquals($this->centralWarehouse->id, $issue->warehouse_id);
        $this->assertEquals('completed', $issue->status);

        // 100 bags * ₹380 = 38,000 + 5 MT * ₹62,000 = 310,000 => Total ₹348,000
        $this->assertEquals(348000.00, (float) $issue->total_cost);
        $this->assertCount(2, $issue->items);

        // Verify StockLedgerEntry records
        $cementIssueLedger = StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('transaction_type', 'site_issue')
            ->where('reference_id', $issue->id)
            ->first();

        $this->assertNotNull($cementIssueLedger);
        $this->assertEquals('out', $cementIssueLedger->direction);
        $this->assertEquals(100.0, (float) $cementIssueLedger->quantity);
        $this->assertEquals(380.0, (float) $cementIssueLedger->unit_rate);
        $this->assertEquals(38000.0, (float) $cementIssueLedger->total_amount);
        $this->assertEquals(400.0, (float) $cementIssueLedger->balance_quantity); // 500 - 100 = 400

        $steelIssueLedger = StockLedgerEntry::where('material_id', $this->steel->id)
            ->where('transaction_type', 'site_issue')
            ->where('reference_id', $issue->id)
            ->first();

        $this->assertNotNull($steelIssueLedger);
        $this->assertEquals('out', $steelIssueLedger->direction);
        $this->assertEquals(5.0, (float) $steelIssueLedger->quantity);
        $this->assertEquals(35.0, (float) $steelIssueLedger->balance_quantity); // 40 - 5 = 35

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'issue_material_to_site',
            'auditable_type' => MaterialIssue::class,
            'auditable_id' => $issue->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_negative_stock_prevention_rejects_issue_exceeding_available_quantity(): void
    {
        // Central Yard has 40 MT steel available. Requesting 50 MT should fail.
        $payload = [
            'issue_number' => 'MIN-OVERDRAFT-01',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'items' => [
                [
                    'material_id' => $this->steel->id,
                    'requested_quantity' => 50,
                    'issued_quantity' => 50,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['items']);

        // Assert no issue record or stock deduction was created
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-OVERDRAFT-01']);
        $this->assertDatabaseMissing('stock_ledger_entries', ['transaction_type' => 'site_issue']);

        // Stock remains untouched at 40 MT
        $totalIn = StockLedgerEntry::where('material_id', $this->steel->id)->where('direction', 'in')->sum('quantity');
        $totalOut = StockLedgerEntry::where('material_id', $this->steel->id)->where('direction', 'out')->sum('quantity');
        $this->assertEquals(40.0, (float) ($totalIn - $totalOut));
    }

    public function test_rejects_issue_when_site_does_not_belong_to_destination_project(): void
    {
        // siteB belongs to projectB, but payload specifies project
        $payload = [
            'issue_number' => 'MIN-MISMATCH-01',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteB->id, // Mismatch!
            'issue_date' => '2026-10-20',
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 20,
                    'issued_quantity' => 20,
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['site_id']);
        $this->assertDatabaseMissing('material_issues', ['issue_number' => 'MIN-MISMATCH-01']);
    }

    public function test_prevent_duplicate_issue_submission(): void
    {
        $payload = [
            'issue_number' => 'MIN-DUP-01',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'items' => [
                [
                    'material_id' => $this->cement->id,
                    'requested_quantity' => 20,
                    'issued_quantity' => 20,
                ],
            ],
        ];

        // First submission succeeds
        $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.store'), $payload)
            ->assertRedirect();

        // Duplicate submission with same issue_number fails validation
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertSessionHasErrors(['issue_number']);
        $this->assertEquals(1, MaterialIssue::where('issue_number', 'MIN-DUP-01')->count());
    }

    public function test_return_unused_materials_from_site_to_warehouse(): void
    {
        // 1. Issue 50 bags of cement to Site A
        $issue = MaterialIssue::create([
            'company_id' => $this->company->id,
            'issue_number' => 'MIN-RET-TEST',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
            'total_cost' => 19000.00,
        ]);

        $item = MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 50,
            'issued_quantity' => 50,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380.00,
            'total_amount' => 19000.00,
        ]);

        StockLedgerEntry::create([
            'company_id' => $this->company->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'entry_date' => '2026-10-20',
            'transaction_type' => 'site_issue',
            'reference_type' => 'material_issues',
            'reference_id' => $issue->id,
            'quantity' => 50,
            'direction' => 'out',
            'unit_rate' => 380.00,
            'total_amount' => 19000.00,
            'balance_quantity' => 450.00, // 500 - 50 = 450
            'created_by' => $this->storekeeper->id,
        ]);

        // 2. Return 15 bags back to warehouse
        $returnPayload = [
            'return_date' => '2026-10-22',
            'returns' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 15,
                    'reason' => 'Surplus stock returned from chainage 14+200',
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.return', $issue), $returnPayload);

        $response->assertRedirect(route('material-issues.show', $issue));

        // Check stock ledger entry
        $returnLedger = StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('transaction_type', 'site_return')
            ->where('reference_id', $issue->id)
            ->first();

        $this->assertNotNull($returnLedger);
        $this->assertEquals('in', $returnLedger->direction);
        $this->assertEquals(15.0, (float) $returnLedger->quantity);
        $this->assertEquals(380.0, (float) $returnLedger->unit_rate);
        $this->assertEquals(5700.0, (float) $returnLedger->total_amount);
        $this->assertEquals(465.0, (float) $returnLedger->balance_quantity); // 450 + 15 = 465

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'return_material_from_site',
            'auditable_type' => MaterialIssue::class,
            'auditable_id' => $issue->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_cannot_return_more_than_issued_quantity(): void
    {
        $issue = MaterialIssue::create([
            'company_id' => $this->company->id,
            'issue_number' => 'MIN-RET-EXCEED',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
            'total_cost' => 3800.00,
        ]);

        $item = MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 10,
            'issued_quantity' => 10,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380.00,
            'total_amount' => 3800.00,
        ]);

        // Attempt to return 20 bags when only 10 were issued
        $returnPayload = [
            'return_date' => '2026-10-22',
            'returns' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 20,
                    'reason' => 'Invalid quantity return',
                ],
            ],
        ];

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.return', $issue), $returnPayload);

        $response->assertSessionHasErrors(['returns']);
        $this->assertDatabaseMissing('stock_ledger_entries', ['transaction_type' => 'site_return']);
    }

    public function test_project_detail_page_displays_site_material_consumption(): void
    {
        $issue = MaterialIssue::create([
            'company_id' => $this->company->id,
            'issue_number' => 'MIN-PROJ-VIEW',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
            'total_cost' => 38000.00,
            'purpose' => 'Deck slab casting',
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 100,
            'issued_quantity' => 100,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380.00,
            'total_amount' => 38000.00,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('projects.show', $this->project));

        $response->assertStatus(200);
        $response->assertSee('Site Material Issues &amp; Inventory Consumption', false);
        $response->assertSee('MIN-PROJ-VIEW');
        $response->assertSee('NSD Peenya Central Yard');
        $response->assertSee('₹38,000.00');
    }

    public function test_printable_issue_slip_renders_delivery_challan_and_gin(): void
    {
        $issue = MaterialIssue::create([
            'company_id' => $this->company->id,
            'issue_number' => 'MIN-PRINT-01',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'issue_date' => '2026-10-20',
            'status' => 'completed',
            'issued_by' => $this->storekeeper->id,
            'received_by_name' => 'K. Raju (Site Engineer)',
            'vehicle_number' => 'KA-04-TR-9988',
            'total_cost' => 38000.00,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'material_id' => $this->cement->id,
            'requested_quantity' => 100,
            'issued_quantity' => 100,
            'unit_of_measure' => 'Bags',
            'unit_rate' => 380.00,
            'total_amount' => 38000.00,
        ]);

        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('material-issues.print', $issue));

        $response->assertStatus(200);
        $response->assertSee('Goods Issue Note (GIN) &amp; Site Dispatch Delivery Challan', false);
        $response->assertSee('MIN-PRINT-01');
        $response->assertSee('KA-04-TR-9988');
        $response->assertSee('NSD EARTH MOVERS &amp; CONSTRUCTION LTD', false);
        $response->assertSee('Ultratech OPC 53 Grade Cement');
    }

    public function test_site_supervisor_without_issue_permission_cannot_execute_issue(): void
    {
        $payload = [
            'issue_number' => 'MIN-UNAUTH-01',
            'warehouse_id' => $this->centralWarehouse->id,
            'project_id' => $this->project->id,
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

        // Site Supervisor has 'materials.request', but does NOT have permission to issue stock from warehouse
        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('material-issues.store'), $payload);

        $response->assertStatus(403);
    }
}
