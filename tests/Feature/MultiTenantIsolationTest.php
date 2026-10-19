<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Company $companyA;
    protected Company $companyB;
    protected Project $projectA;
    protected Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        // MultiTenantIsolationTest explicitly exercises legacy multi-tenant isolation
        config(['company.single_company_mode' => false]);

        // Company A setup
        $this->companyA = Company::create([
            'name' => 'Company Alpha Infrastructure',
            'slug' => 'company-alpha',
        ]);
        $roleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);
        $this->userA = User::create([
            'name' => 'Alpha Admin',
            'email' => 'alpha@test.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyA->users()->attach($this->userA->id, [
            'role_id' => $roleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Company B setup
        $this->companyB = Company::create([
            'name' => 'Company Beta Constructions',
            'slug' => 'company-beta',
        ]);
        $roleB = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);
        $this->userB = User::create([
            'name' => 'Beta Admin',
            'email' => 'beta@test.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->companyB->users()->attach($this->userB->id, [
            'role_id' => $roleB->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Create projects in respective companies
        $this->projectA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alpha Highway Project',
            'code' => 'ALPHA-001',
            'project_type' => 'civil',
            'status' => 'active',
            'contract_amount' => 5000000,
            'budget_amount' => 4500000,
        ]);

        $this->projectB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Beta Highrise Tower',
            'code' => 'BETA-001',
            'project_type' => 'civil',
            'status' => 'active',
            'contract_amount' => 8000000,
            'budget_amount' => 7200000,
        ]);
    }

    public function test_user_only_sees_their_company_projects_in_index(): void
    {
        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get('/projects');

        $response->assertStatus(200);
        $response->assertSee('Alpha Highway Project');
        $response->assertDontSee('Beta Highrise Tower');
    }

    public function test_user_cannot_access_project_belonging_to_another_company(): void
    {
        // User A attempts to view Project B (belongs to Company B)
        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get('/projects/' . $this->projectB->id);

        // Global scope will prevent finding project B under company A context -> 404
        $response->assertStatus(404);
    }

    public function test_user_cannot_switch_to_unauthorized_company_workspace(): void
    {
        // User A attempts to switch to Company B where they have no membership
        $response = $this->actingAs($this->userA)
            ->post('/workspace/switch/' . $this->companyB->id);

        $response->assertStatus(403);
    }

    public function test_user_with_multi_company_membership_can_switch_workspaces_when_mode_disabled(): void
    {
        // Explicitly enable multi-company switching for this regression test
        config(['company.single_company_mode' => false]);

        // Add User A to Company B as well
        $this->companyB->users()->attach($this->userA->id, [
            'status' => 'active',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->userA)
            ->post('/workspace/switch/' . $this->companyB->id);

        $response->assertRedirect('/dashboard');
        $this->assertEquals($this->companyB->id, session('current_company_id'));
    }

    public function test_user_cannot_switch_workspaces_when_single_company_mode_is_active(): void
    {
        config(['company.single_company_mode' => true]);

        $this->companyB->users()->attach($this->userA->id, [
            'status' => 'active',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->userA)
            ->post('/workspace/switch/' . $this->companyB->id);

        $response->assertStatus(403);
    }
}
