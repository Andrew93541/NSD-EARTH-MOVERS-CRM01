<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project-manager',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Project Manager Lead',
            'email' => 'pm@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Mahindra Lifespaces Ltd',
            'email' => 'contact@mahindralife.com',
            'status' => 'active',
        ]);
    }

    public function test_can_create_project_with_valid_data(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/projects', [
                'name' => 'Greenfield Logistics Hub',
                'code' => 'GLH-2026',
                'client_id' => $this->client->id,
                'project_type' => 'civil',
                'location' => 'Chakan MIDC Phase 2, Pune',
                'start_date' => Carbon::today()->format('Y-m-d'),
                'target_completion_date' => Carbon::today()->addMonths(12)->format('Y-m-d'),
                'contract_value' => 12500000.00,
                'approved_budget' => 11000000.00,
                'manager_id' => $this->user->id,
                'description' => 'Construction of heavy industrial flooring and pre-engineered building warehouse.',
            ]);

        $project = Project::where('code', 'GLH-2026')->first();
        $this->assertNotNull($project);
        $response->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'company_id' => $this->company->id,
            'name' => 'Greenfield Logistics Hub',
            'code' => 'GLH-2026',
            'contract_value' => 12500000.00,
            'status' => 'on_track',
        ]);
    }

    public function test_creating_project_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/projects', [
                'name' => '',
                'code' => '',
                'project_type' => 'invalid_type',
            ]);

        $response->assertSessionHasErrors(['name', 'code', 'project_type', 'contract_value', 'approved_budget']);
    }

    public function test_can_view_project_details(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Highway Overpass Section B',
            'code' => 'HWY-OVERPASS-B',
            'project_type' => 'civil',
            'contract_value' => 5000000.00,
            'approved_budget' => 4500000.00,
            'status' => 'active',
            'progress_percent' => 45,
            'manager_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/projects/' . $project->id);

        $response->assertStatus(200);
        $response->assertSee('Highway Overpass Section B');
        $response->assertSee('HWY-OVERPASS-B');
    }

    public function test_can_update_project_status_and_progress(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'City Flyover Ramp 4',
            'code' => 'FLY-RAMP-4',
            'project_type' => 'civil',
            'contract_value' => 4000000.00,
            'approved_budget' => 3600000.00,
            'status' => 'planned',
            'progress_percent' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->put('/projects/' . $project->id, [
                'name' => 'City Flyover Ramp 4 - Revised',
                'project_type' => 'civil',
                'contract_value' => 4200000.00,
                'approved_budget' => 3800000.00,
                'status' => 'on_track',
                'progress_percent' => 25,
            ]);

        $response->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'City Flyover Ramp 4 - Revised',
            'status' => 'on_track',
            'progress_percent' => 25,
        ]);
    }
}
