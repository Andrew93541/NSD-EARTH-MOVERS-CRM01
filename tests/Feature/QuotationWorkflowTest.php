<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $owner;
    protected User $projectManager;
    protected User $accountant;
    protected User $siteSupervisor;
    protected User $storekeeper;
    protected Client $client;

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

        $pmRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'quotations.*', 'clients.*'],
            'is_system' => true,
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*', 'quotations.*'],
            'is_system' => true,
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'materials.request'],
            'is_system' => true,
        ]);

        $storekeeperRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*'],
            'is_system' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'owner@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->owner->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->projectManager = User::create([
            'name' => 'Amit Sharma',
            'email' => 'pm@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->projectManager->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Suresh Reddy',
            'email' => 'accountant@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Vikram Malhotra',
            'email' => 'supervisor@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->siteSupervisor->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->storekeeper = User::create([
            'name' => 'Ramesh Kumar',
            'email' => 'store@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->storekeeper->id, ['role_id' => $storekeeperRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Karnataka Industrial Infra Ltd',
            'company_name' => 'KIADB Infra Division',
            'email' => 'contact@kiadb.gov.in',
            'phone' => '+91 80 2222 3333',
            'gstin' => '29AAACK1234E1Z1',
            'address' => 'Khanija Bhavan, Race Course Road, Bengaluru',
            'status' => 'active',
        ]);
    }

    public function test_project_manager_can_create_quotation_with_transparent_taxes_and_discounts(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-2026-TEST-01',
            'project_title' => 'NH-48 Highway Grading and Earthwork',
            'project_description' => 'Heavy excavation and embankment construction for 5 km stretch',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'tax_type' => 'gst_18',
            'tax_rate' => 18,
            'discount_type' => 'percentage',
            'discount_rate' => 5, // 5% discount
            'terms_conditions' => 'Payment within 30 days of RA bill certification.',
            'items' => [
                [
                    'description' => 'Excavation in hard rock with heavy machinery',
                    'quantity' => 1000,
                    'unit' => 'm3',
                    'unit_rate' => 450,
                ],
                [
                    'description' => 'Granular sub-base (GSB) compaction',
                    'quantity' => 500,
                    'unit' => 'm3',
                    'unit_rate' => 300,
                ],
            ],
        ];

        // Item 1: 1000 * 450 = 450,000
        // Item 2: 500 * 300 = 150,000
        // Subtotal = 600,000
        // Discount 5% = 30,000 => Taxable = 570,000
        // Tax 18% = 102,600
        // Total = 672,600

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.store'), $payload);

        $response->assertRedirect();

        $quotation = Quotation::where('quotation_number', 'EST-2026-TEST-01')->first();
        $this->assertNotNull($quotation);
        $this->assertEquals($this->company->id, $quotation->company_id);
        $this->assertEquals($this->client->id, $quotation->client_id);
        $this->assertEquals('draft', $quotation->status);
        $this->assertEquals(600000.00, (float) $quotation->subtotal);
        $this->assertEquals(30000.00, (float) $quotation->discount_amount);
        $this->assertEquals(18.00, (float) $quotation->tax_rate);
        $this->assertEquals(102600.00, (float) $quotation->tax_amount);
        $this->assertEquals(672600.00, (float) $quotation->total_amount);

        $this->assertCount(2, $quotation->items);
        $this->assertEquals(450000.00, (float) $quotation->items[0]->amount);
        $this->assertEquals(150000.00, (float) $quotation->items[1]->amount);

        // Verify audit log creation
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_quotation',
            'auditable_type' => Quotation::class,
            'auditable_id' => $quotation->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_quotation_calculations_with_exempt_tax_and_flat_discount(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-2026-EXEMPT-01',
            'project_title' => 'Agricultural Canal Earthwork (Exempt)',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'tax_type' => 'exempt',
            'tax_rate' => 0,
            'discount_type' => 'fixed',
            'discount_amount' => 5000,
            'items' => [
                [
                    'description' => 'Canal desilting and bund shaping',
                    'quantity' => 200,
                    'unit' => 'm3',
                    'unit_rate' => 250, // 50,000
                ],
            ],
        ];

        // Subtotal = 50,000
        // Discount = 5,000 => Taxable = 45,000
        // Tax 0% = 0.00
        // Total = 45,000

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.store'), $payload);

        $response->assertRedirect();

        $quotation = Quotation::where('quotation_number', 'EST-2026-EXEMPT-01')->first();
        $this->assertNotNull($quotation);
        $this->assertEquals(50000.00, (float) $quotation->subtotal);
        $this->assertEquals(5000.00, (float) $quotation->discount_amount);
        $this->assertEquals(0.00, (float) $quotation->tax_rate);
        $this->assertEquals(0.00, (float) $quotation->tax_amount);
        $this->assertEquals(45000.00, (float) $quotation->total_amount);
    }

    public function test_valid_status_transitions_workflow(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-TRANS-01',
            'project_title' => 'Transition Test Project',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'draft',
            'tax_treatment' => 'gst_18',
            'tax_rate' => 18,
            'subtotal' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
            'created_by' => $this->projectManager->id,
        ]);

        // 1. draft -> sent
        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->patch(route('quotations.status', $quotation), ['status' => 'sent']);
        $response->assertRedirect();
        $this->assertEquals('sent', $quotation->fresh()->status);

        // 2. sent -> accepted
        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->patch(route('quotations.status', $quotation), ['status' => 'accepted']);
        $response->assertRedirect();
        $this->assertEquals('accepted', $quotation->fresh()->status);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-INVALID-01',
            'project_title' => 'Invalid Transition Test',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'rejected',
            'tax_treatment' => 'gst_18',
            'subtotal' => 10000,
            'tax_amount' => 1800,
            'total_amount' => 11800,
            'created_by' => $this->projectManager->id,
        ]);

        // Attempting to move from 'rejected' back to 'accepted' should be rejected
        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->patch(route('quotations.status', $quotation), ['status' => 'accepted']);

        $response->assertSessionHasErrors(['status']);
        $this->assertEquals('rejected', $quotation->fresh()->status);
    }

    public function test_convert_accepted_quotation_into_project(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-CONVERT-01',
            'project_title' => 'Metro Line 4 Cut and Cover Excavation',
            'project_description' => 'Civil excavation work for underground station access',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'accepted',
            'tax_treatment' => 'gst_18',
            'tax_rate' => 18,
            'subtotal' => 2500000.00,
            'tax_amount' => 450000.00,
            'total_amount' => 2950000.00,
            'created_by' => $this->projectManager->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'item_order' => 1,
            'description' => 'Site clearance and earth cutting',
            'quantity' => 5000,
            'unit' => 'm3',
            'unit_rate' => 500,
            'amount' => 2500000,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.convert', $quotation), [
                'project_name' => 'Metro Line 4 Package 2',
                'start_date' => '2026-11-01',
                'target_completion_date' => '2027-05-31',
                'manager_id' => $this->projectManager->id,
            ]);

        $response->assertRedirect();

        $quotation->refresh();
        $this->assertNotNull($quotation->converted_project_id);
        $this->assertNotNull($quotation->converted_at);
        $this->assertEquals($this->projectManager->id, $quotation->converted_by);

        // Verify created project attributes
        $project = Project::find($quotation->converted_project_id);
        $this->assertNotNull($project);
        $this->assertEquals($this->company->id, $project->company_id);
        $this->assertEquals($this->client->id, $project->client_id);
        $this->assertEquals('Metro Line 4 Package 2', $project->name);
        $this->assertEquals(2950000.00, (float) $project->contract_value);
        $this->assertEquals(2500000.00, (float) $project->approved_budget);
        $this->assertEquals('active', $project->status);

        // Verify original quotation and its line items are preserved intact
        $this->assertEquals('EST-CONVERT-01', $quotation->quotation_number);
        $this->assertCount(1, $quotation->items);
        $this->assertEquals(2500000.00, (float) $quotation->items[0]->amount);
    }

    public function test_prevent_duplicate_project_creation_on_repeated_conversion(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-DUP-01',
            'project_title' => 'Flyover Pier Cap Preparation',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'accepted',
            'tax_treatment' => 'gst_18',
            'subtotal' => 800000.00,
            'tax_amount' => 144000.00,
            'total_amount' => 944000.00,
            'created_by' => $this->projectManager->id,
        ]);

        // First conversion
        $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.convert', $quotation), [
                'project_name' => 'Flyover Pier Cap Prep',
            ]);

        $quotation->refresh();
        $initialProjectId = $quotation->converted_project_id;
        $this->assertNotNull($initialProjectId);

        $initialProjectCount = Project::count();

        // Second conversion attempt via HTTP request
        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.convert', $quotation), [
                'project_name' => 'Flyover Duplicate',
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals($initialProjectCount, Project::count());
        $this->assertEquals($initialProjectId, $quotation->fresh()->converted_project_id);

        // Direct model method call throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already been converted');
        $quotation->convertToProject($this->projectManager);
    }

    public function test_cannot_convert_unaccepted_quotation_into_project(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-DRAFT-CONV',
            'project_title' => 'Draft Estimate',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'draft',
            'tax_treatment' => 'gst_18',
            'subtotal' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
            'created_by' => $this->projectManager->id,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.convert', $quotation), [
                'project_name' => 'Unaccepted Attempt',
            ]);

        $response->assertSessionHas('error');
        $this->assertNull($quotation->fresh()->converted_project_id);
    }

    public function test_printable_quotation_view_renders_successfully(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-PRINT-01',
            'project_title' => 'Industrial Yard Levelling & Grading',
            'project_description' => 'Site preparation including 12000 sqm rough levelling',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'sent',
            'tax_treatment' => 'gst_18',
            'tax_rate' => 18,
            'subtotal' => 300000,
            'tax_amount' => 54000,
            'total_amount' => 354000,
            'created_by' => $this->projectManager->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'item_order' => 1,
            'description' => 'Dozer and Grader Operation',
            'quantity' => 120,
            'unit' => 'hours',
            'unit_rate' => 2500,
            'amount' => 300000,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('quotations.print', $quotation));

        $response->assertStatus(200);
        $response->assertSee('NSD Earth Movers &amp; Construction Ltd', false);
        $response->assertSee('EST-PRINT-01');
        $response->assertSee('Industrial Yard Levelling &amp; Grading', false);
        $response->assertSee('354,000.00');
        $response->assertSee('Karnataka Industrial Infra Ltd');
    }

    public function test_quotation_register_search_and_filters(): void
    {
        Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-FILTER-DRAFT',
            'project_title' => 'Bridge Foundation Work',
            'date' => '2026-10-20',
            'valid_until' => '2026-11-20',
            'status' => 'draft',
            'tax_treatment' => 'gst_18',
            'subtotal' => 100000,
            'total_amount' => 118000,
            'created_by' => $this->projectManager->id,
        ]);

        Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_number' => 'EST-FILTER-SENT',
            'project_title' => 'Culvert Construction',
            'date' => '2026-10-21',
            'valid_until' => '2026-11-21',
            'status' => 'sent',
            'tax_treatment' => 'gst_18',
            'subtotal' => 200000,
            'total_amount' => 236000,
            'created_by' => $this->projectManager->id,
        ]);

        // Filter by status=draft
        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('quotations.index', ['status' => 'draft']));

        $response->assertStatus(200);
        $response->assertSee('EST-FILTER-DRAFT');
        $response->assertDontSee('EST-FILTER-SENT');

        // Search by keyword
        $searchResponse = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('quotations.index', ['search' => 'Culvert']));

        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('EST-FILTER-SENT');
        $searchResponse->assertDontSee('EST-FILTER-DRAFT');
    }

    public function test_unauthorized_roles_are_blocked_from_managing_quotations(): void
    {
        // Storekeeper cannot create or manage quotations
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('quotations.create'));

        $response->assertStatus(403);

        // Site Supervisor cannot create quotations
        $responseSup = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('quotations.create'));

        $responseSup->assertStatus(403);
    }
}
