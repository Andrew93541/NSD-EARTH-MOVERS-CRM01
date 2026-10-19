<?php

namespace Tests\Feature\Workflows;

use App\Models\Account;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Models\JournalEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDisbursementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_payroll_calculation_approval_disbursement_and_gl_posting(): void
    {
        // 1. Setup Company
        $company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
            'currency' => 'INR',
            'is_active' => true,
        ]);
        session(['current_company_id' => $company->id]);

        // Roles
        $hrRole = Role::create([
            'company_id' => $company->id,
            'name' => 'HR Officer',
            'slug' => 'hr_officer',
            'permissions' => ['payroll.*', 'attendance.*'],
            'is_system' => true,
        ]);

        $accountantRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['payroll.view', 'payroll.disburse', 'finance.*'],
            'is_system' => true,
        ]);

        $hrUser = User::factory()->create(['name' => 'HR Manager']);
        $company->users()->attach($hrUser->id, ['role_id' => $hrRole->id, 'status' => 'active', 'is_primary' => true]);

        $accountantUser = User::factory()->create(['name' => 'Finance Controller']);
        $company->users()->attach($accountantUser->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => false]);

        // Setup General Ledger accounts
        $bankAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $laborAccount = Account::create([
            'company_id' => $company->id,
            'code' => '5010',
            'name' => 'Direct Site Labor & Staff Wages',
            'account_type' => 'expense',
            'sub_type' => 'direct_cost',
            'is_active' => true,
        ]);

        // Shift & Employee
        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Standard Shift',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'standard_hours' => 8.00,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'code' => 'EMP-001',
            'first_name' => 'Ramesh',
            'last_name' => 'Kumar',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 800.00,
            'shift_id' => $shift->id,
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ]);

        // Configure Employee Salary Structure
        EmployeeSalaryStructure::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'effective_from' => '2026-10-01',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 800.00,
            'hourly_overtime_rate' => 150.00,
            'standard_allowance' => 500.00,
            'statutory_deduction_percent' => 5.00,
        ]);

        // 2. Setup 10 Approved Daily Attendance records in October 2026
        for ($day = 1; $day <= 10; $day++) {
            $date = sprintf('2026-10-%02d', $day);
            DailyAttendance::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'status' => 'present',
                'hours_worked' => 8.00,
                'overtime_hours' => ($day === 1) ? 4.00 : 0.00, // 4 hours OT on day 1
                'is_overtime_approved' => true,
                'approval_status' => 'approved',
                'approved_by' => $hrUser->id,
            ]);
        }

        // 3. HR calculates payroll for October 2026
        $startDate = '2026-10-01';
        $endDate = '2026-10-31';

        $response = $this->actingAs($hrUser)->post(route('payroll.store'), [
            'title' => 'October 2026 Labor Payroll',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', [
            'company_id' => $company->id,
            'title' => 'October 2026 Labor Payroll',
            'status' => 'processing',
        ]);

        $period = PayrollPeriod::where('company_id', $company->id)->firstOrFail();

        // Verify calculations:
        // 10 days * 800 = 8000 basic pay
        // 4 hrs OT * 150 = 600 overtime pay
        // 500 allowance
        // 8000 * 5% = 400 deduction
        // Net pay = (8000 + 600 + 500) - 400 = 8700
        $record = PayrollRecord::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertEquals(10, $record->days_worked);
        $this->assertEquals('8000.00', $record->basic_pay);
        $this->assertEquals('600.00', $record->overtime_pay);
        $this->assertEquals('500.00', $record->allowances);
        $this->assertEquals('400.00', $record->deductions);
        $this->assertEquals('8700.00', $record->net_pay);
        $this->assertEquals('8700.00', $period->total_disbursement);

        // 4. Try disburse BEFORE approval -> must be rejected
        $prematureDisburse = $this->actingAs($accountantUser)->post(route('payroll.disburse', $period));
        $prematureDisburse->assertSessionHasErrors('status');
        $this->assertEquals('processing', $period->fresh()->status);

        // 5. HR approves payroll
        $approveResponse = $this->actingAs($hrUser)->post(route('payroll.approve', $period));
        $approveResponse->assertRedirect();
        $this->assertEquals('approved', $period->fresh()->status);
        $this->assertEquals('approved', $record->fresh()->status);

        // 6. Finance Accountant disburses payroll
        $disburseResponse = $this->actingAs($accountantUser)->post(route('payroll.disburse', $period));
        $disburseResponse->assertRedirect();

        $freshPeriod = $period->fresh();
        $this->assertEquals('disbursed', $freshPeriod->status);
        $this->assertEquals('paid', $record->fresh()->status);

        // 7. Verify Balanced GL Journal Entry was posted atomically
        $journal = JournalEntry::where('company_id', $company->id)
            ->where('status', 'posted')
            ->first();

        $this->assertNotNull($journal);
        $this->assertEquals('8700.00', $journal->total_amount);
        $this->assertTrue($journal->isBalanced());

        // Verify Dr 5010 and Cr 1010
        $debitLine = $journal->lines()->where('account_id', $laborAccount->id)->first();
        $creditLine = $journal->lines()->where('account_id', $bankAccount->id)->first();

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals('8700.00', $debitLine->debit);
        $this->assertEquals('0.00', $debitLine->credit);
        $this->assertEquals('0.00', $creditLine->debit);
        $this->assertEquals('8700.00', $creditLine->credit);

        // 8. Verify Payslip View is accessible
        $payslipResponse = $this->actingAs($hrUser)->get(route('payroll.payslip', [$period, $record]));
        $payslipResponse->assertOk();
        $payslipResponse->assertSee('Ramesh Kumar');
        $payslipResponse->assertSee('8,700');

        // 9. Verify disbursed payroll is immutable
        $reApproveResponse = $this->actingAs($hrUser)->post(route('payroll.approve', $period));
        $reApproveResponse->assertSessionHasErrors('status');
    }
}
