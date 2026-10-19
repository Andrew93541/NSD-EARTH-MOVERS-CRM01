<?php

namespace Tests\Feature;

use App\Events\AttendanceUpdated;
use App\Events\MaterialStockUpdated;
use App\Events\PayrollStatusUpdated;
use App\Events\ProjectStatusUpdated;
use App\Events\PurchaseRequestUpdated;
use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebSocketAndBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $otherCompany;
    protected Role $pmRole;
    protected Role $supervisorRole;
    protected Role $accountantRole;
    protected User $user1;
    protected User $user2;
    protected User $pmUser;
    protected User $supervisorUser;
    protected Project $project;
    protected Site $site;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'legal_name' => 'NSD Earth Movers & Construction Limited',
            'slug' => 'nsd-earth-movers',
            'code' => 'NSD',
            'status' => 'active',
        ]);

        $this->otherCompany = Company::create([
            'name' => 'Competitor Corp',
            'legal_name' => 'Competitor Infrastructure Ltd',
            'slug' => 'competitor-corp',
            'code' => 'COMP',
            'status' => 'active',
        ]);

        config(['company.default_company_id' => $this->company->id]);
        config(['company.single_company_mode' => true]);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '12345',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        require base_path('routes/channels.php');

        $this->pmRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.view', 'projects.manage', 'inventory.issue'],
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.manage'],
        ]);

        $this->accountantRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['payroll.view', 'payroll.approve'],
        ]);

        $this->user1 = User::create([
            'name' => 'User One',
            'email' => 'user1@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->user1->companies()->attach($this->company->id, [
            'role_id' => $this->pmRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->user2->companies()->attach($this->otherCompany->id, [
            'role_id' => null,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->pmUser = User::create([
            'name' => 'Project Manager Bob',
            'email' => 'bob@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->pmUser->companies()->attach($this->company->id, [
            'role_id' => $this->pmRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->supervisorUser = User::create([
            'name' => 'Supervisor Alice',
            'email' => 'alice@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->supervisorUser->companies()->attach($this->company->id, [
            'role_id' => $this->supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'JSW Steel Plant Expansion',
            'code' => 'PRJ-JSW-001',
            'project_type' => 'civil',
            'contract_value' => 5000000.00,
            'approved_budget' => 4500000.00,
            'status' => 'on_track',
            'manager_id' => $this->pmUser->id,
            'assigned_team' => [$this->user1->id],
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Blast Furnace Site #2',
            'code' => 'SITE-BF2',
            'supervisor_id' => $this->supervisorUser->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Central Depot',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);
    }

    public function test_user_private_channel_authorization(): void
    {
        // User 1 authorized on their own channel
        $response = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.' . $this->user1->id,
            ]);
        $response->assertOk();

        // User 1 denied on User 2's channel
        $response = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.' . $this->user2->id,
            ]);
        $response->assertForbidden();
    }

    public function test_company_tenant_channel_authorization(): void
    {
        // User 1 belongs to NSD Company
        $response = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-company.' . $this->company->id,
            ]);
        $response->assertOk();

        // User 1 denied for other company
        $response = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-company.' . $this->otherCompany->id,
            ]);
        $response->assertForbidden();
    }

    public function test_project_channel_authorization_for_manager_and_team(): void
    {
        // Project Manager authorized
        $response = $this->actingAs($this->pmUser)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-project.' . $this->project->id,
            ]);
        $response->assertOk();

        // User 1 in assigned_team authorized
        $response = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-project.' . $this->project->id,
            ]);
        $response->assertOk();

        // User 2 from other company denied
        $response = $this->actingAs($this->user2)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-project.' . $this->project->id,
            ]);
        $response->assertForbidden();
    }

    public function test_site_channel_authorization_for_assigned_supervisor(): void
    {
        // Assigned site supervisor authorized
        $response = $this->actingAs($this->supervisorUser)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-site.' . $this->site->id,
            ]);
        $response->assertOk();

        // Project Manager of site's project authorized
        $response = $this->actingAs($this->pmUser)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-site.' . $this->site->id,
            ]);
        $response->assertOk();

        // User 2 denied
        $response = $this->actingAs($this->user2)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-site.' . $this->site->id,
            ]);
        $response->assertForbidden();
    }

    public function test_project_status_updated_event_payload_and_channels(): void
    {
        $event = new ProjectStatusUpdated($this->project);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals('private-project.' . $this->project->id, $channels[0]->name);
        $this->assertEquals('private-company.' . $this->company->id, $channels[1]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals($this->project->id, $payload['id']);
        $this->assertEquals('JSW Steel Plant Expansion', $payload['name']);
        $this->assertEquals('on_track', $payload['status']);
        $this->assertTrue($event->afterCommit);
    }

    public function test_purchase_request_updated_event_payload_and_channels(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->warehouse->id,
            'requested_by' => $this->user1->id,
            'pr_number' => 'PR-2026-001',
            'title' => 'Urgent Cement Bags',
            'status' => 'pending',
            'priority' => 'high',
            'request_date' => now()->toDateString(),
            'current_approval_level' => 1,
        ]);

        $event = new PurchaseRequestUpdated($pr);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-company.' . $this->company->id, $channels[0]->name);
        $this->assertEquals('private-App.Models.User.' . $this->user1->id, $channels[1]->name);
        $this->assertEquals('private-project.' . $this->project->id, $channels[2]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals('PR-2026-001', $payload['pr_number']);
        $this->assertEquals('pending', $payload['status']);
        $this->assertTrue($event->afterCommit);
    }

    public function test_material_stock_updated_event_payload_and_channels(): void
    {
        $event = new MaterialStockUpdated(
            $this->company->id,
            $this->warehouse->id,
            'Central Depot',
            101,
            'OPC 53 Cement',
            25.500,
            'bags',
            true
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals('private-company.' . $this->company->id, $channels[0]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals(101, $payload['material_id']);
        $this->assertEquals(25.500, $payload['current_balance']);
        $this->assertTrue($payload['is_low_stock']);
    }

    public function test_attendance_updated_event_payload_and_channels(): void
    {
        $event = new AttendanceUpdated(
            $this->company->id,
            $this->site->id,
            'Blast Furnace Site #2',
            '2026-10-19',
            15
        );

        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertEquals('private-site.' . $this->site->id, $channels[0]->name);
        $this->assertEquals('private-company.' . $this->company->id, $channels[1]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals(15, $payload['records_count']);
        $this->assertEquals('2026-10-19', $payload['date']);
    }

    public function test_payroll_status_updated_event_payload_and_channels(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'title' => 'October 2026 Wages',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'approved',
            'total_disbursement' => 450000.00,
            'created_by' => $this->user1->id,
        ]);

        $event = new PayrollStatusUpdated($period);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals('private-company.' . $this->company->id . '.role.accountant', $channels[0]->name);
        $this->assertEquals('private-company.' . $this->company->id . '.role.owner', $channels[1]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals('approved', $payload['status']);
        $this->assertEquals(450000.00, $payload['total_disbursement']);
    }

    public function test_send_operational_reminders_command_executes_cleanly(): void
    {
        $this->artisan('buildflow:send-reminders')
            ->assertExitCode(0);
    }
}
