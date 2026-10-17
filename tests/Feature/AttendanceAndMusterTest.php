<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceAndMusterTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected Company $company;
    protected Project $project;
    protected Site $site;
    protected Employee $worker1;
    protected Employee $worker2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Engineer',
            'slug' => 'site-engineer',
            'permissions' => ['*'],
        ]);

        $this->supervisor = User::create([
            'name' => 'Site Supervisor Kumar',
            'email' => 'site@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->supervisor->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Riverside Residency High-Rise',
            'code' => 'RIV-RES-01',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Tower A & Basement Excavation',
            'code' => 'SITE-TOW-A',
            'supervisor_id' => $this->supervisor->id,
            'status' => 'active',
        ]);

        $this->worker1 = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-LAB-001',
            'first_name' => 'Ramesh',
            'last_name' => 'Patel',
            'employment_type' => 'daily_wage',
            'daily_rate' => 750.00,
            'status' => 'active',
        ]);

        $this->worker2 = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-LAB-002',
            'first_name' => 'Suresh',
            'last_name' => 'Gaikwad',
            'employment_type' => 'daily_wage',
            'daily_rate' => 700.00,
            'status' => 'active',
        ]);
    }

    public function test_can_view_muster_roll(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/attendance/muster-roll?site_id=' . $this->site->id . '&date=' . Carbon::today()->toDateString());

        $response->assertStatus(200);
        $response->assertSee('Labor Muster Roll');
        $response->assertSee('Ramesh Patel');
        $response->assertSee('Suresh Gaikwad');
    }

    public function test_can_record_daily_muster_attendance_with_overtime(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->supervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/attendance/muster-roll', [
                'site_id' => $this->site->id,
                'date' => $today,
                'attendance' => [
                    $this->worker1->id => [
                        'status' => 'present',
                        'overtime_hours' => 2.0,
                    ],
                    $this->worker2->id => [
                        'status' => 'half_day',
                        'overtime_hours' => 0.0,
                    ],
                ],
            ]);

        $response->assertRedirect(route('attendance.muster', ['site_id' => $this->site->id, 'date' => $today]));
        $response->assertSessionHas('success');

        $rec1 = DailyAttendance::where('employee_id', $this->worker1->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $this->assertNotNull($rec1);
        $this->assertEquals($this->company->id, $rec1->company_id);
        $this->assertEquals($this->site->id, $rec1->site_id);
        $this->assertEquals('present', $rec1->status);
        $this->assertEquals(8.00, $rec1->worked_hours);
        $this->assertEquals(2.00, $rec1->overtime_hours);
        $this->assertTrue((bool) $rec1->is_overtime_approved);

        $rec2 = DailyAttendance::where('employee_id', $this->worker2->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $this->assertNotNull($rec2);
        $this->assertEquals($this->company->id, $rec2->company_id);
        $this->assertEquals($this->site->id, $rec2->site_id);
        $this->assertEquals('half_day', $rec2->status);
        $this->assertEquals(4.00, $rec2->worked_hours);
        $this->assertEquals(0.00, $rec2->overtime_hours);
    }

    public function test_raw_biometric_punches_are_traceable(): void
    {
        $punchTime = Carbon::now()->subHours(8);

        $punch = AttendancePunch::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->worker1->id,
            'site_id' => $this->site->id,
            'punch_time' => $punchTime,
            'punch_type' => 'in',
            'source' => 'biometric_import',
            'device_id' => 'ESSL-BIO-SITE1',
            'raw_metadata' => ['device_sn' => 'BIO998234', 'imported_file' => 'punch_log_oct17.dat'],
            'logged_by' => $this->supervisor->id,
        ]);

        $this->assertDatabaseHas('attendance_punches', [
            'id' => $punch->id,
            'company_id' => $this->company->id,
            'employee_id' => $this->worker1->id,
            'source' => 'biometric_import',
            'device_id' => 'ESSL-BIO-SITE1',
        ]);

        $this->assertEquals('biometric_import', $punch->source);
        $this->assertEquals('ESSL-BIO-SITE1', $punch->device_id);
    }
}
