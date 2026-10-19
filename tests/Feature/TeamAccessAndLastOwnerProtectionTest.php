<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamAccessAndLastOwnerProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Role $ownerRole;
    protected Role $adminRole;
    protected Role $surveyorRole;
    protected User $ownerUser;
    protected User $adminUser;
    protected User $secondUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $this->ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['team.*', 'projects.*', 'attendance.*', 'billing.*'],
            'is_system' => true,
        ]);

        $this->surveyorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Quantity Surveyor',
            'slug' => 'quantity_surveyor',
            'permissions' => ['projects.view', 'subcontractors.view'],
            'is_system' => false,
        ]);

        $this->ownerUser = User::create([
            'name' => 'Sukhvinder Singh',
            'email' => 'owner@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->ownerUser->id, ['role_id' => $this->ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->adminUser = User::create([
            'name' => 'Amitabh Sen',
            'email' => 'admin@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'status' => 'active']);

        $this->secondUser = User::create([
            'name' => 'Vikram Roy',
            'email' => 'vikram@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->secondUser->id, ['role_id' => $this->surveyorRole->id, 'status' => 'active']);
    }

    public function test_can_view_team_directory_and_role_matrix(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('team.index'));

        $response->assertStatus(200);
        $response->assertSee('Team & User Directory', false);
        $response->assertSee('Sukhvinder Singh');
        $response->assertSee('Amitabh Sen');

        $roleResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('team.roles'));

        $roleResponse->assertStatus(200);
        $roleResponse->assertSee('Roles & Permission Matrix', false);
        $roleResponse->assertSee('Quantity Surveyor');
    }

    public function test_non_owner_cannot_self_escalate_privileges(): void
    {
        // Admin attempts to change their own role to Owner
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('team.users.role', $this->adminUser), [
                'role_id' => $this->ownerRole->id,
                'status' => 'active',
            ]);

        $response->assertStatus(403);

        $pivot = DB::table('company_user')->where('user_id', $this->adminUser->id)->first();
        $this->assertEquals($this->adminRole->id, $pivot->role_id);
    }

    public function test_system_blocks_demoting_or_removing_last_company_owner(): void
    {
        // Only one active owner exists (Sukhvinder Singh).
        // Attempting to demote the owner to Administrator must fail with HTTP 403
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('team.users.role', $this->ownerUser), [
                'role_id' => $this->adminRole->id,
                'status' => 'active',
            ]);

        $response->assertStatus(403);

        $pivot = DB::table('company_user')->where('user_id', $this->ownerUser->id)->first();
        $this->assertEquals($this->ownerRole->id, $pivot->role_id);
        $this->assertEquals('active', $pivot->status);

        // Deactivating the last owner must also fail
        $response2 = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('team.users.role', $this->ownerUser), [
                'role_id' => $this->ownerRole->id,
                'status' => 'inactive',
            ]);

        $response2->assertStatus(403);
        $this->assertEquals('active', DB::table('company_user')->where('user_id', $this->ownerUser->id)->value('status'));
    }

    public function test_can_create_custom_role_with_audit_trail(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('team.roles.store'), [
                'name' => 'Senior Billing Engineer',
                'slug' => 'senior_billing_eng',
                'description' => 'Oversees RA bills and subcontractor measurement books.',
                'permissions' => ['billing.*', 'measurement_books.*', 'subcontractor_bills.*'],
            ]);

        $response->assertSessionHas('success');

        $role = Role::where('slug', 'senior_billing_eng')->first();
        $this->assertNotNull($role);
        $this->assertEquals('Senior Billing Engineer', $role->name);
        $this->assertTrue($role->hasPermission('billing.create'));
        $this->assertFalse($role->hasPermission('payroll.disburse'));

        // Verify audit log creation
        $log = AuditLog::where('auditable_type', Role::class)
            ->where('auditable_id', $role->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('create_role', $log->action);
        $this->assertEquals($this->ownerUser->id, $log->user_id);
    }
}
