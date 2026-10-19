<?php

namespace Tests\Feature\Workflows;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\DailyAttendanceCorrection;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceMusterApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Role $ownerRole;
    protected Role $supervisorRole;
    protected Role $pmRole;
    protected User $owner;
    protected User $supervisor;
    protected User $pm;
    protected Project $project;
    protected Site $site;
    protected Employee $worker1;
    protected Employee $worker2;

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

        $this->ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->pmRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'attendance.*'],
            'is_system' => true,
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'attendance.record'],
            'is_system' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'owner@nsdinfra.test',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->owner->id, ['role_id' => $this->ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->pm = User::create([
            'name' => 'Amit PM',
            'email' => 'pm@nsdinfra.test',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->pm->id, ['role_id' => $this->pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->supervisor = User::create([
            'name' => 'Vikram Supervisor',
            'email' => 'supervisor@nsdinfra.test',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->supervisor->id, ['role_id' => $this->supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Highway Expansion Phase 1',
            'code' => 'PRJ-HWY-01',
            'status' => 'active',
            'manager_id' => $this->pm->id,
            'contract_value' => 5000000.00,
            'approved_budget' => 4200000.00,
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Sector 4 Earthworks Site',
            'code' => 'SITE-SEC-4',
            'supervisor_id' => $this->supervisor->id,
            'status' => 'active',
        ]);

        $this->worker1 = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-EXC-01',
            'first_name' => 'Sanjay',
            'last_name' => 'Rao',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 850.00,
            'status' => 'active',
        ]);

        $this->worker2 = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-HLP-02',
            'first_name' => 'Dinesh',
            'last_name' => 'Yadav',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 600.00,
            'status' => 'active',
        ]);
    }

    /**
     * Test complete Workflow 2: Submission as pending -> Approval by PM -> Correction flow -> Lockout on disbursed payroll.
     */
    public function test_complete_attendance_muster_submission_and_approval_workflow(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. Site Supervisor submits daily muster roll (action = submit)
        $submitResponse = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/attendance/muster-roll', [
                'site_id' => $this->site->id,
                'date' => $today,
                'action' => 'submit',
                'attendance' => [
                    $this->worker1->id => [
                        'status' => 'present',
                        'overtime_hours' => 2.5,
                    ],
                    $this->worker2->id => [
                        'status' => 'half_day',
                        'overtime_hours' => 0.0,
                    ],
                ],
            ]);

        $submitResponse->assertRedirect();

        $att1 = DailyAttendance::where('employee_id', $this->worker1->id)->whereDate('attendance_date', $today)->first();
        $this->assertNotNull($att1);
        $this->assertSame('pending_approval', $att1->approval_status);
        $this->assertFalse((bool) $att1->is_overtime_approved);
        $this->assertNull($att1->approved_by);

        // 2. Project Manager reviews and approves muster
        $approveResponse = $this->actingAs($this->pm)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.approve'), [
                'attendance_ids' => [$att1->id],
            ]);

        $approveResponse->assertRedirect();
        $att1->refresh();
        $this->assertSame('approved', $att1->approval_status);
        $this->assertSame($this->pm->id, (int) $att1->approved_by);
        $this->assertNotNull($att1->approved_at);

        // 3. Correction Request Flow
        $correctionResponse = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.corrections.store'), [
                'daily_attendance_id' => $att1->id,
                'proposed_status' => 'present',
                'proposed_worked_hours' => 8.0,
                'proposed_overtime_hours' => 3.0,
                'reason' => 'Worker worked 3.0 OT hours on night compaction instead of 2.5.',
            ]);

        $correctionResponse->assertRedirect();
        $correction = DailyAttendanceCorrection::where('daily_attendance_id', $att1->id)->first();
        $this->assertNotNull($correction);
        $this->assertSame('pending', $correction->status);

        // PM approves correction
        $pmCorrectionResponse = $this->actingAs($this->pm)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.corrections.approve', $correction));

        $pmCorrectionResponse->assertRedirect();
        $correction->refresh();
        $att1->refresh();
        $this->assertSame('approved', $correction->status);
        $this->assertEquals(3.0, (float) $att1->overtime_hours);

        // 4. Lockout when payroll period is disbursed
        PayrollPeriod::create([
            'company_id' => $this->company->id,
            'title' => 'Settled Month',
            'start_date' => Carbon::today()->startOfMonth()->toDateString(),
            'end_date' => Carbon::today()->endOfMonth()->toDateString(),
            'total_disbursement' => 150000.00,
            'status' => 'disbursed',
        ]);

        $blockedCorrection = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.corrections.store'), [
                'daily_attendance_id' => $att1->id,
                'proposed_status' => 'absent',
                'reason' => 'Attempted modification after payroll disbursement.',
            ]);

        $blockedCorrection->assertSessionHasErrors(['reason']);
    }
}
