<?php

namespace Tests\Feature;

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

class AttendanceApprovalAndCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisorA;
    protected User $supervisorB;
    protected User $hrManager;
    protected Company $company;
    protected Project $project;
    protected Site $siteA;
    protected Site $siteB;
    protected Employee $workerA;
    protected Employee $officeWorker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.view', 'attendance.record', 'attendance.approve'],
        ]);

        $hrRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'HR Manager',
            'slug' => 'hr_officer',
            'permissions' => ['attendance.*', 'employees.*', 'payroll.*'],
        ]);

        $this->supervisorA = User::create([
            'name' => 'Supervisor Site A',
            'email' => 'supa@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->supervisorA->id, ['role_id' => $supervisorRole->id, 'status' => 'active']);

        $this->supervisorB = User::create([
            'name' => 'Supervisor Site B',
            'email' => 'supb@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->supervisorB->id, ['role_id' => $supervisorRole->id, 'status' => 'active']);

        $this->hrManager = User::create([
            'name' => 'HR Kavita',
            'email' => 'hr@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->hrManager->id, ['role_id' => $hrRole->id, 'status' => 'active']);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Highland Infrastructure Phase 1',
            'code' => 'HLD-01',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Site Alpha Quarry',
            'code' => 'SITE-ALP',
            'supervisor_id' => $this->supervisorA->id,
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Site Beta Tunnel',
            'code' => 'SITE-BET',
            'supervisor_id' => $this->supervisorB->id,
            'status' => 'active',
        ]);

        $this->workerA = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->siteA->id,
            'code' => 'LAB-A-01',
            'first_name' => 'Jagdish',
            'last_name' => 'Kumar',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 800.00,
            'status' => 'active',
        ]);

        $this->officeWorker = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => null, // Head Office
            'code' => 'OFF-01',
            'first_name' => 'Meera',
            'last_name' => 'Nair',
            'employment_type' => 'permanent',
            'monthly_salary' => 45000.00,
            'status' => 'active',
        ]);
    }

    public function test_site_supervisor_can_submit_and_approve_attendance_for_their_site(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->supervisorA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.muster.store'), [
                'site_id' => $this->siteA->id,
                'date' => $today,
                'action' => 'approve',
                'attendance' => [
                    $this->workerA->id => [
                        'status' => 'present',
                        'overtime_hours' => 2.0,
                    ],
                ],
            ]);

        $response->assertSessionHas('success');

        $record = DailyAttendance::where('employee_id', $this->workerA->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('approved', $record->approval_status);
        $this->assertEquals($this->supervisorA->id, $record->approved_by);
        $this->assertNotNull($record->approved_at);
    }

    public function test_unauthorized_supervisor_cannot_approve_attendance_for_another_site(): void
    {
        $today = Carbon::today()->toDateString();

        $att = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'employee_id' => $this->workerA->id,
            'attendance_date' => $today,
            'status' => 'present',
            'worked_hours' => 8.00,
            'approval_status' => 'pending_approval',
        ]);

        // Supervisor B tries to approve attendance for Site A
        $response = $this->actingAs($this->supervisorB)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.approve'), [
                'attendance_ids' => [$att->id],
            ]);

        $response->assertStatus(403);
        $this->assertEquals('pending_approval', $att->fresh()->approval_status);
    }

    public function test_rejection_requires_valid_reason(): void
    {
        $today = Carbon::today()->toDateString();

        $att = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'employee_id' => $this->workerA->id,
            'attendance_date' => $today,
            'status' => 'present',
            'worked_hours' => 8.00,
            'approval_status' => 'pending_approval',
        ]);

        // Rejection without reason fails validation
        $response = $this->actingAs($this->supervisorA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.reject'), [
                'attendance_ids' => [$att->id],
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors('rejection_reason');

        // Rejection with reason succeeds
        $response2 = $this->actingAs($this->supervisorA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.reject'), [
                'attendance_ids' => [$att->id],
                'rejection_reason' => 'Worker left site at 10 AM without supervisor permission.',
            ]);

        $response2->assertSessionHas('success');
        $this->assertEquals('rejected', $att->fresh()->approval_status);
        $this->assertStringContainsString('Worker left site', $att->fresh()->rejection_reason);
    }

    public function test_approved_attendance_cannot_be_directly_modified_and_requires_correction(): void
    {
        $today = Carbon::today()->toDateString();

        $att = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'employee_id' => $this->workerA->id,
            'attendance_date' => $today,
            'status' => 'present',
            'worked_hours' => 8.00,
            'overtime_hours' => 0.00,
            'approval_status' => 'approved',
            'approved_by' => $this->supervisorA->id,
        ]);

        // Attempting to overwrite approved attendance directly fails
        $response = $this->actingAs($this->supervisorA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.muster.store'), [
                'site_id' => $this->siteA->id,
                'date' => $today,
                'attendance' => [
                    $this->workerA->id => [
                        'status' => 'half_day',
                        'overtime_hours' => 0.0,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertEquals('present', $att->fresh()->status);

        // Submitting an auditable correction request succeeds
        $corrResponse = $this->actingAs($this->supervisorA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.corrections.store'), [
                'daily_attendance_id' => $att->id,
                'proposed_status' => 'half_day',
                'proposed_worked_hours' => 4.00,
                'reason' => 'Biometric clock malfunction recorded full day instead of half day.',
            ]);

        $corrResponse->assertSessionHas('success');

        $correction = DailyAttendanceCorrection::where('daily_attendance_id', $att->id)->first();
        $this->assertNotNull($correction);
        $this->assertEquals('pending', $correction->status);
        $this->assertEquals('half_day', $correction->proposed_status);

        // Approving the correction applies it to the attendance record
        $approveResponse = $this->actingAs($this->supervisorA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('attendance.corrections.approve', $correction));

        $approveResponse->assertSessionHas('success');
        $this->assertEquals('approved', $correction->fresh()->status);
        $this->assertEquals('half_day', $att->fresh()->status);
        $this->assertEquals(4.00, (float) $att->fresh()->worked_hours);
    }

    public function test_payroll_strictly_excludes_unapproved_and_draft_attendance(): void
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        // 5 Approved days
        for ($i = 1; $i <= 5; $i++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->siteA->id,
                'employee_id' => $this->workerA->id,
                'attendance_date' => Carbon::now()->startOfMonth()->addDays($i - 1)->toDateString(),
                'status' => 'present',
                'worked_hours' => 8.00,
                'approval_status' => 'approved',
                'approved_by' => $this->supervisorA->id,
            ]);
        }

        // 5 Pending / Unapproved days
        for ($i = 6; $i <= 10; $i++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->siteA->id,
                'employee_id' => $this->workerA->id,
                'attendance_date' => Carbon::now()->startOfMonth()->addDays($i - 1)->toDateString(),
                'status' => 'present',
                'worked_hours' => 8.00,
                'approval_status' => 'pending_approval',
            ]);
        }

        // Generate payroll batch
        $response = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Test Gated Payroll',
                'start_date' => $start,
                'end_date' => $end,
            ]);

        $response->assertSessionHas('success');

        $period = PayrollPeriod::where('title', 'Test Gated Payroll')->firstOrFail();
        $record = $period->records()->where('employee_id', $this->workerA->id)->firstOrFail();

        // Must count only 5 approved days, NOT 10!
        $this->assertEquals(5, $record->days_worked);
        $this->assertEquals(4000.00, (float) $record->basic_pay); // 5 * 800 = 4000
    }
}
