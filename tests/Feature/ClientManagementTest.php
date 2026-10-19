<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;
    protected User $siteSupervisorUser;
    protected Company $companyA;
    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        // Company A (Active test company)
        $this->companyA = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $ownerRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->ownerUser = User::create([
            'name' => 'Narayan Shinde',
            'email' => 'owner@nsdconstruction.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->companyA->users()->attach($this->ownerUser->id, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Site Supervisor (Restricted Role without client manage rights)
        $supervisorRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'daily_logs.*', 'attendance.record', 'materials.request'],
        ]);

        $this->siteSupervisorUser = User::create([
            'name' => 'Ramesh Patil',
            'email' => 'supervisor@nsdconstruction.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->companyA->users()->attach($this->siteSupervisorUser->id, [
            'role_id' => $supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Company B (Other tenant)
        $this->companyB = Company::create([
            'name' => 'Competitor Infra Pvt Ltd',
            'slug' => 'competitor-infra',
        ]);
    }

    public function test_authorized_user_can_view_client_list_scoped_to_active_company(): void
    {
        $clientA = Client::create([
            'company_id' => $this->companyA->id,
            'name' => 'Tata Realty and Infrastructure',
            'email' => 'contact@tatarealty.in',
            'status' => 'active',
        ]);

        $clientB = Client::create([
            'company_id' => $this->companyB->id,
            'name' => 'Larsen & Toubro Infra',
            'email' => 'procurement@lnt.in',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertSee('Tata Realty and Infrastructure');
        $response->assertDontSee('Larsen & Toubro Infra');
    }

    public function test_can_create_client_with_valid_data(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('clients.store'), [
                'name' => 'National Highways Authority of India',
                'company_name' => 'NHAI PIU Pune',
                'contact_person' => 'Er. Sanjay Kulkarni, Project Director',
                'email' => 'pd.pune@nhai.gov.in',
                'phone' => '+91 20 2553 4567',
                'gstin' => '27AAALN0123A1Z9',
                'pan' => 'AAALN0123A',
                'billing_address' => 'NHAI Bhavan, Sector 10, Dwarka, New Delhi',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'status' => 'active',
            ]);

        $client = Client::where('gstin', '27AAALN0123A1Z9')->first();
        $this->assertNotNull($client);
        $response->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', [
            'company_id' => $this->companyA->id,
            'name' => 'National Highways Authority of India',
            'gstin' => '27AAALN0123A1Z9',
            'city' => 'Pune',
            'status' => 'active',
        ]);
    }

    public function test_client_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('clients.store'), [
                'name' => '',
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors(['name', 'status']);
    }

    public function test_client_gstin_uniqueness_is_scoped_to_company(): void
    {
        Client::create([
            'company_id' => $this->companyA->id,
            'name' => 'Adani Ports & SEZ',
            'gstin' => '24AAACA0123B1ZT',
            'status' => 'active',
        ]);

        // Attempting to create duplicate GSTIN in Company A should fail
        $responseA = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('clients.store'), [
                'name' => 'Adani Logistics Hub',
                'gstin' => '24AAACA0123B1ZT',
                'status' => 'active',
            ]);

        $responseA->assertSessionHasErrors(['gstin']);

        // But registering the same GSTIN in Company B is allowed
        $clientInCompanyB = Client::create([
            'company_id' => $this->companyB->id,
            'name' => 'Adani Logistics Hub (Subsidiary)',
            'gstin' => '24AAACA0123B1ZT',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('clients', [
            'id' => $clientInCompanyB->id,
            'company_id' => $this->companyB->id,
            'gstin' => '24AAACA0123B1ZT',
        ]);
    }

    public function test_can_view_client_profile_with_associated_projects_and_billing(): void
    {
        $client = Client::create([
            'company_id' => $this->companyA->id,
            'name' => 'Godrej Properties Ltd',
            'email' => 'procurement@godrej.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $this->companyA->id,
            'client_id' => $client->id,
            'name' => 'Godrej Emerald Greens Site Development',
            'code' => 'GEG-2026',
            'contract_value' => 8500000.00,
            'approved_budget' => 7500000.00,
            'status' => 'on_track',
            'progress_percent' => 30,
        ]);

        $invoice = ClientInvoice::create([
            'company_id' => $this->companyA->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'invoice_number' => 'INV-2026-001',
            'issue_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
            'total_amount' => 2500000.00,
            'paid_amount' => 1000000.00,
            'status' => 'partially_paid',
            'created_by' => $this->ownerUser->id,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('clients.show', $client));

        $response->assertStatus(200);
        $response->assertSee('Godrej Properties Ltd');
        $response->assertSee('Godrej Emerald Greens Site Development');
        $response->assertSee('INV-2026-001');
        $response->assertSee('8,500,000.00'); // Contract Value
        $response->assertSee('2,500,000.00'); // Bills raised
        $response->assertSee('1,000,000.00'); // Money received
        $response->assertSee('1,500,000.00'); // Outstanding balance
    }

    public function test_can_update_client_details(): void
    {
        $client = Client::create([
            'company_id' => $this->companyA->id,
            'name' => 'PMRDA Authority',
            'contact_person' => 'Old Contact',
            'phone' => '+91 20 1111 2222',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('clients.update', $client), [
                'name' => 'PMRDA Urban Development Authority',
                'contact_person' => 'Er. Vikram Joshi, Executive Engineer',
                'phone' => '+91 20 9999 8888',
                'email' => 'ee.works@pmrda.gov.in',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'PMRDA Urban Development Authority',
            'contact_person' => 'Er. Vikram Joshi, Executive Engineer',
            'phone' => '+91 20 9999 8888',
        ]);
    }

    public function test_tenant_isolation_cannot_view_or_edit_client_from_another_company(): void
    {
        $clientOtherCompany = Client::create([
            'company_id' => $this->companyB->id,
            'name' => 'Confidential Client B',
            'status' => 'active',
        ]);

        // Try viewing
        $viewResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('clients.show', $clientOtherCompany));
        $viewResponse->assertStatus(404);

        // Try editing
        $editResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('clients.edit', $clientOtherCompany));
        $editResponse->assertStatus(404);

        // Try updating
        $updateResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('clients.update', $clientOtherCompany), [
                'name' => 'Hijacked Client Name',
                'status' => 'active',
            ]);
        $updateResponse->assertStatus(404);

        $this->assertDatabaseMissing('clients', [
            'id' => $clientOtherCompany->id,
            'name' => 'Hijacked Client Name',
        ]);
    }

    public function test_unauthorized_user_cannot_access_client_management_via_direct_http_request(): void
    {
        // Site supervisor should be forbidden (403) from managing clients
        $responseList = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('clients.index'));
        $responseList->assertStatus(403);

        $responseCreate = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('clients.create'));
        $responseCreate->assertStatus(403);

        $responseStore = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('clients.store'), [
                'name' => 'Unauthorized Client Inc',
                'status' => 'active',
            ]);
        $responseStore->assertStatus(403);
    }

    public function test_client_directory_navigation_visibility_for_authorized_and_unauthorized_roles(): void
    {
        // Authorized owner sees link
        $authResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('dashboard'));
        $authResponse->assertStatus(200);
        $authResponse->assertSee('Client Directory');

        // Unauthorized supervisor does NOT see link
        $unauthResponse = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('dashboard'));
        $unauthResponse->assertStatus(200);
        $unauthResponse->assertDontSee('Client Directory');
    }
}
