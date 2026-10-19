<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SingleCompanyArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Company $nsdCompany;
    protected User $owner;
    protected User $projectManager;
    protected User $siteSupervisor;
    protected User $accountant;
    protected User $storekeeper;
    protected Role $ownerRole;
    protected Role $pmRole;
    protected Role $siteRole;
    protected Role $accountantRole;
    protected Role $storekeeperRole;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Single Configured Company: NSD Earth Movers & Construction Ltd
        $this->nsdCompany = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'legal_name' => 'NSD Earth Movers & Infrastructure Private Limited',
            'slug' => 'nsd-earth-movers',
            'email' => 'contact@nsdinfra.com',
            'phone' => '+91 80 4123 5678',
            'gstin' => '29AAACN1234F1Z5',
            'pan' => 'AAACN1234F',
            'address' => 'Plot 42, Peenya Industrial Area 2nd Phase, Bengaluru, Karnataka 560058',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        session(['current_company_id' => $this->nsdCompany->id]);

        // 2. Roles
        $this->ownerRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->pmRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'clients.*', 'suppliers.view', 'materials.request', 'attendance.view', 'attendance.muster'],
            'is_system' => true,
        ]);

        $this->siteRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'attendance.*', 'materials.request'],
            'is_system' => true,
        ]);

        $this->accountantRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*', 'accounting.*', 'expenses.*', 'payroll.*', 'retention.*', 'clients.view', 'suppliers.view'],
            'is_system' => true,
        ]);

        $this->storekeeperRole = Role::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*', 'procurement.*', 'suppliers.view'],
            'is_system' => true,
        ]);

        // 3. Users
        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'admin@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->owner->id, [
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->projectManager = User::create([
            'name' => 'Amit Sharma',
            'email' => 'pm@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->projectManager->id, [
            'role_id' => $this->pmRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->siteSupervisor = User::create([
            'name' => 'Vikram Malhotra',
            'email' => 'site@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->siteSupervisor->id, [
            'role_id' => $this->siteRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Sneha Roy',
            'email' => 'accounts@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->accountant->id, [
            'role_id' => $this->accountantRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->storekeeper = User::create([
            'name' => 'Gopal Das',
            'email' => 'storekeeper@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->nsdCompany->users()->attach($this->storekeeper->id, [
            'role_id' => $this->storekeeperRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    public function test_single_company_profile_displays_nsd_corporate_identity(): void
    {
        $response = $this->actingAs($this->owner)->get(route('company.profile'));

        $response->assertStatus(200);
        $response->assertSee('NSD Earth Movers &amp; Construction Ltd', false);
        $response->assertSee('NSD Earth Movers &amp; Infrastructure Private Limited', false);
        $response->assertSee('29AAACN1234F1Z5');
        $response->assertSee('AAACN1234F');
        $response->assertSee('Plot 42, Peenya Industrial Area 2nd Phase');
    }

    public function test_owner_can_update_corporate_profile_with_audit_trail(): void
    {
        $response = $this->actingAs($this->owner)->put(route('company.profile.update'), [
            'legal_name' => 'NSD Earth Movers & Infrastructure Private Limited',
            'email' => 'corporate@nsdinfra.com',
            'phone' => '+91 80 4123 9999',
            'address' => 'Plot 42-A, Peenya Industrial Area, Bengaluru, Karnataka 560058',
            'gstin' => '29AAACN1234F1Z5',
            'pan' => 'AAACN1234F',
        ]);

        $response->assertRedirect(route('company.profile'));
        $this->nsdCompany->refresh();
        $this->assertEquals('corporate@nsdinfra.com', $this->nsdCompany->email);
        $this->assertEquals('+91 80 4123 9999', $this->nsdCompany->phone);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'company_id' => $this->nsdCompany->id,
            'action' => 'company.profile.updated',
        ]);
    }

    public function test_site_supervisor_cannot_update_corporate_profile(): void
    {
        $response = $this->actingAs($this->siteSupervisor)->put(route('company.profile.update'), [
            'legal_name' => 'Unauthorized Change',
            'email' => 'hacked@nsdinfra.com',
            'phone' => '+91 9999999999',
            'address' => 'Fake Address',
        ]);

        $response->assertStatus(403);
    }

    public function test_multi_company_creation_is_disabled_in_single_company_mode(): void
    {
        // GET /workspace/create should be forbidden
        $responseGet = $this->actingAs($this->owner)->get(route('workspace.create'));
        $responseGet->assertStatus(403);

        // POST /workspace/create should be forbidden
        $responsePost = $this->actingAs($this->owner)->post(route('workspace.store'), [
            'name' => 'Second Fake Company Ltd',
        ]);
        $responsePost->assertStatus(403);
    }

    public function test_workspace_select_bypasses_directly_to_dashboard_for_active_nsd_employee(): void
    {
        $response = $this->actingAs($this->owner)->get(route('workspace.select'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_unassigned_user_cannot_access_nsd_dashboard(): void
    {
        $unassignedUser = User::create([
            'name' => 'External Intruder',
            'email' => 'intruder@other.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->actingAs($unassignedUser)->get(route('dashboard'));
        $response->assertRedirect(route('workspace.select'));
    }

    public function test_server_side_role_permissions_enforced_on_financial_modules(): void
    {
        // 1. Accountant can access billing and accounting
        $accBilling = $this->actingAs($this->accountant)->get(route('billing.index'));
        $accBilling->assertStatus(200);

        $accAccounting = $this->actingAs($this->accountant)->get(route('accounting.index'));
        $accAccounting->assertStatus(200);

        // 2. Site supervisor cannot access accounting or billing
        $siteAccounting = $this->actingAs($this->siteSupervisor)->get(route('accounting.index'));
        $siteAccounting->assertStatus(403);
    }

    public function test_new_records_automatically_bind_to_configured_company(): void
    {
        // Act as PM and create a client and project
        $this->actingAs($this->projectManager);

        $client = Client::create([
            'name' => 'Tata Housing Development',
            'contact_person' => 'Ramesh Kumar',
            'email' => 'ramesh@tatahousing.com',
            'phone' => '+91 80 5555 1234',
            'status' => 'active',
        ]);

        $this->assertEquals($this->nsdCompany->id, $client->company_id);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Prestige Park Heights',
            'code' => 'PRJ-2026-TEST',
            'project_type' => 'civil',
            'contract_amount' => 15000000.00,
            'budget_amount' => 13500000.00,
            'status' => 'active',
        ]);

        $this->assertEquals($this->nsdCompany->id, $project->company_id);
    }

    public function test_dashboard_and_phase_a_b_routes_function_seamlessly_in_single_company_mode(): void
    {
        $client = Client::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Sobha Developers Ltd',
            'status' => 'active',
        ]);

        $supplier = Supplier::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'JSW Steel Corporation',
            'vendor_type' => 'supplier',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $client->id,
            'name' => 'Metro Line 3 Civil Works',
            'code' => 'PRJ-2026-METRO',
            'contract_amount' => 45000000.00,
            'budget_amount' => 40000000.00,
            'status' => 'active',
        ]);

        // Dashboard renders cleanly
        $this->actingAs($this->owner)->get(route('dashboard'))->assertStatus(200);

        // Clients directory renders cleanly
        $this->actingAs($this->projectManager)->get(route('clients.index'))->assertStatus(200)->assertSee('Sobha Developers Ltd');

        // Suppliers directory renders cleanly
        $this->actingAs($this->storekeeper)->get(route('suppliers.index'))->assertStatus(200)->assertSee('JSW Steel Corporation');

        // Project overview renders cleanly with financial overview
        $this->actingAs($this->projectManager)->get(route('projects.show', $project))->assertStatus(200)->assertSee('Metro Line 3 Civil Works');
    }

    public function test_switching_to_another_company_is_strictly_forbidden_in_single_company_mode(): void
    {
        $secondCompany = Company::create([
            'name' => 'Apex Infra Developers Pvt Ltd',
            'slug' => 'apex-infra',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        // Even if user has an active membership in another company record in the DB
        $secondCompany->users()->attach($this->owner->id, [
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
            'is_primary' => false,
        ]);

        // Switching away from NSD must be blocked with HTTP 403
        $response = $this->actingAs($this->owner)->post(route('workspace.switch', $secondCompany));
        $response->assertStatus(403);

        // Session current_company_id remains NSD company
        $this->assertEquals($this->nsdCompany->id, session('current_company_id'));
    }

    public function test_direct_http_requests_to_financial_endpoints_enforce_role_boundaries(): void
    {
        // 1. Site Supervisor attempts direct access to Loans & EMI -> 403 Forbidden
        $this->actingAs($this->siteSupervisor)->get(route('loans.index'))->assertStatus(403);

        // 2. Storekeeper attempts direct access to Client Invoicing & RA Billing -> 403 Forbidden
        $this->actingAs($this->storekeeper)->get(route('billing.index'))->assertStatus(403);

        // 3. Storekeeper attempts direct access to General Ledger & Accounting -> 403 Forbidden
        $this->actingAs($this->storekeeper)->get(route('accounting.index'))->assertStatus(403);

        // 4. Site Supervisor attempts direct access to Client Retention & DLP Claims -> 403 Forbidden
        $this->actingAs($this->siteSupervisor)->get(route('retention.index'))->assertStatus(403);

        // 5. Unauthenticated guest attempts access to Company Profile -> Redirect to login
        auth()->logout();
        $this->get(route('company.profile'))->assertRedirect(route('login'));
    }

    public function test_client_and_supplier_relationships_and_financial_metrics_remain_strictly_linked(): void
    {
        $client = Client::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Bangalore Metro Rail Corporation',
            'company_name' => 'BMRCL',
            'contact_person' => 'Chief Engineer',
            'email' => 'contracts@bmrcl.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $this->nsdCompany->id,
            'client_id' => $client->id,
            'name' => 'Reach 6 Underground Tunnel',
            'code' => 'BMRCL-UG-06',
            'contract_amount' => 50000000.00,
            'approved_budget' => 45000000.00,
            'status' => 'active',
        ]);

        $supplier = Supplier::create([
            'company_id' => $this->nsdCompany->id,
            'name' => 'Ultratech Ready-Mix Concrete',
            'vendor_type' => 'supplier',
            'payment_terms' => 'net_30',
            'status' => 'active',
        ]);

        // Relationships are strictly maintained
        $this->assertTrue($client->projects->contains($project));
        $this->assertEquals($client->id, $project->client->id);
        $this->assertEquals($this->nsdCompany->id, $client->company_id);
        $this->assertEquals($this->nsdCompany->id, $supplier->company_id);
        $this->assertEquals($this->nsdCompany->id, $project->company_id);

        // Project show endpoint loads financial overview without errors
        $response = $this->actingAs($this->projectManager)->get(route('projects.show', $project));
        $response->assertStatus(200);
        $response->assertSee('Reach 6 Underground Tunnel');
        $response->assertSee('BMRCL');
    }

    public function test_user_without_nsd_membership_cannot_resolve_workspace_or_access_application(): void
    {
        $otherCompany = Company::create([
            'name' => 'Third Party Builders Ltd',
            'slug' => 'third-party-builders',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        $externalUser = User::create([
            'name' => 'External Contractor',
            'email' => 'contractor@external.com',
            'password' => Hash::make('Password123!'),
        ]);

        // Attach user only to third-party company
        $otherCompany->users()->attach($externalUser->id, [
            'status' => 'active',
            'is_primary' => true,
        ]);

        // In single-company mode, externalUser must resolve null company
        $this->assertNull($externalUser->currentCompany());
        $this->assertFalse(session()->has('current_company_id'));

        // Direct web request to /dashboard must be redirected to workspace.select
        $response = $this->actingAs($externalUser)->get(route('dashboard'));
        $response->assertRedirect(route('workspace.select'));

        // Visiting workspace.select renders no options and explicit warning
        $selectResponse = $this->actingAs($externalUser)->get(route('workspace.select'));
        $selectResponse->assertStatus(200);
        $selectResponse->assertSee('No active company workspace assigned or membership revoked');
        $selectResponse->assertDontSee(route('workspace.switch', $otherCompany));

        // API / JSON request to authenticated route is aborted with 403 Forbidden
        $jsonResponse = $this->actingAs($externalUser)->getJson(route('dashboard'));
        $jsonResponse->assertStatus(403);
    }

    public function test_suspended_nsd_employee_cannot_resolve_workspace_or_access_application(): void
    {
        // Suspend the site supervisor's membership in NSD company
        $this->nsdCompany->users()->updateExistingPivot($this->siteSupervisor->id, [
            'status' => 'suspended',
        ]);

        // currentCompany() returns null
        $this->assertNull($this->siteSupervisor->fresh()->currentCompany());

        // Web access is redirected to workspace.select
        $response = $this->actingAs($this->siteSupervisor)->get(route('dashboard'));
        $response->assertRedirect(route('workspace.select'));

        // JSON / API access returns 403 Forbidden
        $jsonResponse = $this->actingAs($this->siteSupervisor)->getJson(route('dashboard'));
        $jsonResponse->assertStatus(403);
    }

    public function test_configured_company_resolves_deterministically_to_nsd_earth_movers(): void
    {
        $resolved = Company::configuredCompany();
        $this->assertNotNull($resolved);
        $this->assertEquals('nsd-earth-movers', $resolved->slug);
        $this->assertEquals('NSD Earth Movers & Construction Ltd', $resolved->name);
        $this->assertEquals($this->nsdCompany->id, Company::configuredCompanyId());
    }
}
