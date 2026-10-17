<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollCalculationAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrManager;
    protected Company $company;
    protected Project $project;
    protected Site $site;
    protected Employee $salariedStaff;
    protected Employee $dailyWageWorker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'HR & Payroll Manager',
            'slug' => 'hr-manager',
            'permissions' => ['*'],
        ]);

        $this->hrManager = User::create([
            'name' => 'Kavita Sharma',
            'email' => 'hr@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->hrManager->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'High-Speed Rail Flyover',
            'code' => 'HSR-01',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Pier 42 Excavation Site',
            'code' => 'SITE-P42',
            'status' => 'active',
        ]);

        // Permanent salaried site supervisor
        $this->salariedStaff = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-SAL-01',
            'first_name' => 'Anil',
            'last_name' => 'Verma',
            'designation' => 'Site Engineer',
            'employment_type' => 'permanent',
            'monthly_salary' => 52000.00,
            'daily_wage_rate' => 0.00,
            'status' => 'active',
        ]);

        // Daily wage machine operator
        $this->dailyWageWorker = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-OP-01',
            'first_name' => 'Babu',
            'last_name' => 'Rao',
            'designation' => 'JCB Excavator Operator',
            'employment_type' => 'machine_operator',
            'daily_wage_rate' => 900.00,
            'monthly_salary' => 0.00,
            'status' => 'active',
        ]);
    }

    public function test_can_view_payroll_index_page(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('payroll.index'));

        $response->assertStatus(200);
        $response->assertSee('Workforce Payroll');
        $response->assertSee('Generate New Payroll Batch');
    }

    public function test_can_view_create_payroll_batch_form(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('payroll.create'));

        $response->assertStatus(200);
        $response->assertSee('Generate Payroll Batch');
        $response->assertSee('Batch Title');
    }

    public function test_can_generate_payroll_batch_from_attendance_and_overtime(): void
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        // Create 20 days present attendance with overtime for daily wage operator
        for ($i = 1; $i <= 20; $i++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->site->id,
                'employee_id' => $this->dailyWageWorker->id,
                'attendance_date' => Carbon::now()->startOfMonth()->addDays($i - 1)->toDateString(),
                'status' => 'present',
                'overtime_hours' => ($i <= 5) ? 2.00 : 0.00, // Total 10 hours overtime
            ]);
        }

        // Create 26 days attendance for permanent supervisor
        for ($i = 1; $i <= 26; $i++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->site->id,
                'employee_id' => $this->salariedStaff->id,
                'attendance_date' => Carbon::now()->startOfMonth()->addDays($i - 1)->toDateString(),
                'status' => 'present',
                'overtime_hours' => 0.00,
            ]);
        }

        $postData = [
            'title' => Carbon::now()->format('F Y') . ' Operations Payroll',
            'start_date' => $start,
            'end_date' => $end,
        ];

        $response = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('payroll.store'), $postData);

        $period = PayrollPeriod::where('title', $postData['title'])->first();
        $this->assertNotNull($period);
        $response->assertRedirect(route('payroll.show', $period));

        $this->assertEquals('processing', $period->status);
        $this->assertGreaterThan(0, (float) $period->total_disbursement);

        // Check daily wage worker calculation:
        // Basic: 20 days * 900 = 18,000
        // Overtime rate: (900/8) * 1.5 = 168.75/hr. 10 hrs = 1,687.50
        $opRecord = PayrollRecord::where('payroll_period_id', $period->id)
            ->where('employee_id', $this->dailyWageWorker->id)
            ->first();
        $this->assertNotNull($opRecord);
        $this->assertEquals(20, $opRecord->days_worked);
        $this->assertEquals(10.00, (float) $opRecord->overtime_hours);
        $this->assertEquals(18000.00, (float) $opRecord->basic_pay);
        $this->assertEquals(1687.50, (float) $opRecord->overtime_pay);

        // Check salaried supervisor calculation:
        // Basic: 52,000 (>=26 days worked)
        // Allowances: 1500 (worked > 10 days)
        // Deductions: 5% PF = 2600
        // Net pay: 52000 + 1500 - 2600 = 50900
        $salRecord = PayrollRecord::where('payroll_period_id', $period->id)
            ->where('employee_id', $this->salariedStaff->id)
            ->first();
        $this->assertNotNull($salRecord);
        $this->assertEquals(26, $salRecord->days_worked);
        $this->assertEquals(52000.00, (float) $salRecord->basic_pay);
        $this->assertEquals(1500.00, (float) $salRecord->allowances);
        $this->assertEquals(2600.00, (float) $salRecord->deductions);
        $this->assertEquals(50900.00, (float) $salRecord->net_pay);
    }

    public function test_can_approve_and_disburse_payroll_batch(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'title' => 'Mid-Month Interim Payroll',
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->startOfMonth()->addDays(14)->toDateString(),
            'total_disbursement' => 25000.00,
            'status' => 'processing',
        ]);

        PayrollRecord::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->dailyWageWorker->id,
            'days_worked' => 12,
            'days_absent' => 2,
            'overtime_hours' => 4.00,
            'basic_pay' => 10800.00,
            'allowances' => 0.00,
            'overtime_pay' => 675.00,
            'deductions' => 0.00,
            'net_pay' => 11475.00,
            'status' => 'pending',
        ]);

        // Step 1: Approve batch
        $approveResponse = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('payroll.approve', $period));

        $approveResponse->assertRedirect(route('payroll.show', $period));
        $period->refresh();
        $this->assertEquals('approved', $period->status);
        $this->assertEquals($this->hrManager->id, $period->approved_by);
        $this->assertEquals('approved', $period->records->first()->status);

        // Step 2: Disburse batch
        $disburseResponse = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('payroll.disburse', $period));

        $disburseResponse->assertRedirect(route('payroll.show', $period));
        $period->refresh();
        $this->assertEquals('disbursed', $period->status);
        $this->assertEquals('paid', $period->records->first()->status);

        // Step 3: Cannot re-approve disbursed batch
        $reApproveResponse = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('payroll.approve', $period));

        $reApproveResponse->assertSessionHasErrors('status');
    }

    public function test_can_view_worker_payslip(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'title' => 'October 2026 Batch',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'total_disbursement' => 50900.00,
            'status' => 'approved',
        ]);

        $record = PayrollRecord::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->salariedStaff->id,
            'days_worked' => 26,
            'days_absent' => 0,
            'overtime_hours' => 0,
            'basic_pay' => 52000.00,
            'allowances' => 1500.00,
            'overtime_pay' => 0.00,
            'deductions' => 2600.00,
            'net_pay' => 50900.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('payroll.payslip', [$period, $record]));

        $response->assertStatus(200);
        $response->assertSee('Worker Salary Slip');
        $response->assertSee('Anil Verma');
        $response->assertSee('EMP-SAL-01');
        $response->assertSee('52,000.00');
        $response->assertSee('50,900.00');
        $response->assertSee('Statutory PF');
    }

    public function test_cannot_create_duplicate_payroll_period_for_same_dates(): void
    {
        $start = '2026-10-01';
        $end = '2026-10-31';

        PayrollPeriod::create([
            'company_id' => $this->company->id,
            'title' => 'First Batch',
            'start_date' => $start,
            'end_date' => $end,
            'total_disbursement' => 10000.00,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Second Duplicate Batch',
                'start_date' => $start,
                'end_date' => $end,
            ]);

        $response->assertSessionHasErrors('start_date');
    }
}
