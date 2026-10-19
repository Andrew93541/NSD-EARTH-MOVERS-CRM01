<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NoRefreshInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Role $ownerRole;
    protected Role $supervisorRole;
    protected User $owner;
    protected User $supervisor;
    protected Project $project;
    protected Site $site;
    protected Warehouse $warehouse;
    protected Employee $employee;

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

        config(['company.default_company_id' => $this->company->id]);
        config(['company.single_company_mode' => true]);

        $this->ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.view', 'attendance.create', 'attendance.edit', 'attendance.muster'],
        ]);

        $this->owner = User::create([
            'name' => 'Director Patel',
            'email' => 'patel@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->owner->companies()->attach($this->company->id, [
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->supervisor = User::create([
            'name' => 'Supervisor Ram',
            'email' => 'ram@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->supervisor->companies()->attach($this->company->id, [
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
            'manager_id' => $this->owner->id,
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Blast Furnace Site #2',
            'code' => 'SITE-BF2',
            'supervisor_id' => $this->supervisor->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Central Depot',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'first_name' => 'Mohan',
            'last_name' => 'Kumar',
            'code' => 'EMP-001',
            'designation' => 'Excavator Operator',
            'status' => 'active',
            'daily_wage_rate' => 850.00,
            'joining_date' => '2025-01-01',
        ]);
    }

    public function test_muster_roll_submission_via_ajax_returns_json_success(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson('/attendance/muster-roll', [
                'date' => $today,
                'site_id' => $this->site->id,
                'project_id' => $this->project->id,
                'attendance' => [
                    $this->employee->id => [
                        'status' => 'present',
                        'overtime_hours' => 2.0,
                        'remarks' => 'Morning shift excavated pit #3',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        $record = \App\Models\DailyAttendance::where('company_id', $this->company->id)
            ->where('employee_id', $this->employee->id)
            ->where('site_id', $this->site->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals($today, $record->attendance_date->toDateString());
        $this->assertEquals('present', $record->status);
        $this->assertEquals(2.0, (float) $record->overtime_hours);
    }

    public function test_muster_roll_submission_fails_with_json_when_period_disbursed(): void
    {
        $targetDate = '2026-09-15';

        PayrollPeriod::create([
            'company_id' => $this->company->id,
            'title' => 'September 2026 Payroll',
            'period_type' => 'monthly',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'disbursed',
            'total_disbursement' => 350000.00,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson('/attendance/muster-roll', [
                'date' => $targetDate,
                'site_id' => $this->site->id,
                'project_id' => $this->project->id,
                'attendance' => [
                    $this->employee->id => [
                        'status' => 'present',
                        'overtime_hours' => 0,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_purchase_request_ajax_approval_workflow(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->warehouse->id,
            'requested_by' => $this->supervisor->id,
            'pr_number' => 'PR-2026-088',
            'title' => 'Hydraulic Oil 200L',
            'status' => 'pending',
            'priority' => 'urgent',
            'request_date' => now()->toDateString(),
            'current_approval_level' => 1,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/purchase-requests/{$pr->id}/approve", [
                'remarks' => 'Approved for immediate dispatch',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'approved',
            'approved_by' => $this->owner->id,
        ]);
    }

    public function test_purchase_request_ajax_rejection_workflow(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'warehouse_id' => $this->warehouse->id,
            'requested_by' => $this->supervisor->id,
            'pr_number' => 'PR-2026-089',
            'title' => 'Excess Safety Helmets',
            'status' => 'pending',
            'priority' => 'low',
            'request_date' => now()->toDateString(),
            'current_approval_level' => 1,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/purchase-requests/{$pr->id}/reject", [
                'rejection_reason' => 'Existing stock sufficient until next quarter',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'rejected',
        ]);
    }

    public function test_notification_ajax_mark_as_read_updates_counter(): void
    {
        $notification = AppNotification::create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'title' => 'Material Stock Critical',
            'message' => 'TMT Rebar 16mm below minimum safety threshold',
            'type' => 'warning',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->patchJson("/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'unread_count' => 0,
        ]);

        $this->assertTrue((bool) $notification->fresh()->is_read);
    }

    public function test_notification_ajax_mark_all_read(): void
    {
        AppNotification::create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'title' => 'Note 1',
            'message' => 'Message 1',
            'type' => 'info',
            'is_read' => false,
        ]);

        AppNotification::create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'title' => 'Note 2',
            'message' => 'Message 2',
            'type' => 'info',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson('/notifications/mark-all-read');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'unread_count' => 0,
        ]);

        $this->assertEquals(0, AppNotification::where('user_id', $this->owner->id)->where('is_read', false)->count());
    }

    public function test_profile_preferences_ajax_update(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile/preferences', [
                'theme' => 'dark',
                'notifications_browser' => true,
                'notifications_email' => false,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Display and notification preferences saved.',
        ]);

        $user = $this->owner->fresh();
        $this->assertEquals('dark', $user->preferences['theme']);
        $this->assertTrue($user->preferences['notifications_browser']);
        $this->assertFalse($user->preferences['notifications_email']);
    }
}
