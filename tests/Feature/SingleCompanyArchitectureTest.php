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
}
