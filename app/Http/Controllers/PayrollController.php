<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $periods = PayrollPeriod::with(['approver', 'records.employee'])
            ->latest('start_date')
            ->paginate(10);

        $stats = [
            'total_disbursed' => PayrollPeriod::whereIn('status', ['approved', 'disbursed'])->sum('total_disbursement'),
            'pending_approval' => PayrollPeriod::whereIn('status', ['draft', 'processing'])->count(),
            'active_employees' => Employee::where('status', 'active')->count(),
            'latest_period' => PayrollPeriod::latest('end_date')->first(),
        ];

        return view('payroll.index', compact('periods', 'stats'));
    }

    public function create(): View
    {
        $defaultStart = Carbon::now()->startOfMonth()->toDateString();
        $defaultEnd = Carbon::now()->endOfMonth()->toDateString();
        $defaultTitle = Carbon::now()->format('F Y') . ' Muster Payroll';

        return view('payroll.create', compact('defaultStart', 'defaultEnd', 'defaultTitle'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $companyId = session('current_company_id');

        $exists = PayrollPeriod::whereDate('start_date', $validated['start_date'])
            ->whereDate('end_date', $validated['end_date'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'start_date' => 'A payroll period covering these exact dates already exists for this company.',
            ]);
        }

        $activeEmployees = Employee::where('status', 'active')->get();

        if ($activeEmployees->isEmpty()) {
            return back()->withInput()->withErrors([
                'title' => 'No active employees found to generate payroll.',
            ]);
        }

        $period = DB::transaction(function () use ($validated, $activeEmployees) {
            $period = PayrollPeriod::create([
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_disbursement' => 0.00,
                'status' => 'processing',
            ]);

            $totalDisbursement = 0.00;

            foreach ($activeEmployees as $emp) {
                // Fetch daily attendance records in this period
                $attendance = DailyAttendance::where('employee_id', $emp->id)
                    ->whereBetween('attendance_date', [$validated['start_date'], $validated['end_date']])
                    ->get();

                $presentCount = $attendance->where('status', 'present')->count();
                $halfDayCount = $attendance->where('status', 'half_day')->count();
                $absentCount = $attendance->where('status', 'absent')->count();

                $daysWorked = $presentCount + ($halfDayCount * 0.5);
                $overtimeHours = (float) $attendance->sum('overtime_hours');

                // Determine base compensation
                $basicPay = 0.00;
                $hourlyOtRate = 0.00;

                if ($emp->employment_type === 'daily_wage' || $emp->employment_type === 'machine_operator') {
                    $dailyRate = (float) ($emp->daily_wage_rate ?: ($emp->monthly_salary / 26));
                    $basicPay = $daysWorked * $dailyRate;
                    $hourlyOtRate = ($dailyRate / 8) * 1.5;
                } else {
                    // Permanent or salaried staff: full salary if worked >= 26 days or pro-rated
                    $monthlySalary = (float) $emp->monthly_salary;
                    $basicPay = ($daysWorked >= 26) ? $monthlySalary : round(($daysWorked / 26) * $monthlySalary, 2);
                    $hourlyOtRate = ($monthlySalary / 208) * 1.5;
                }

                $overtimePay = round($overtimeHours * $hourlyOtRate, 2);

                // Standard site allowance if worked > 10 days
                $allowances = ($daysWorked >= 10 && $emp->employment_type !== 'daily_wage') ? 1500.00 : 0.00;

                // Statutory deduction (e.g. 5% PF for salaried staff)
                $deductions = ($emp->employment_type === 'permanent') ? round($basicPay * 0.05, 2) : 0.00;

                $netPay = round(($basicPay + $allowances + $overtimePay) - $deductions, 2);
                $totalDisbursement += $netPay;

                PayrollRecord::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $emp->id,
                    'days_worked' => (int) round($daysWorked),
                    'days_absent' => $absentCount,
                    'overtime_hours' => $overtimeHours,
                    'basic_pay' => $basicPay,
                    'allowances' => $allowances,
                    'overtime_pay' => $overtimePay,
                    'deductions' => $deductions,
                    'net_pay' => $netPay,
                    'status' => 'pending',
                ]);
            }

            $period->update(['total_disbursement' => $totalDisbursement]);

            return $period;
        });

        return redirect()->route('payroll.show', $period)
            ->with('success', "Payroll batch '{$period->title}' calculated successfully for " . count($activeEmployees) . " employees.");
    }

    public function show(PayrollPeriod $period): View
    {
        $period->load(['approver', 'records.employee.assignedSite']);

        return view('payroll.show', compact('period'));
    }

    public function approve(PayrollPeriod $period): RedirectResponse
    {
        if ($period->status === 'disbursed') {
            return back()->withErrors(['status' => 'Disbursed payroll periods cannot be modified.']);
        }

        $period->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        $period->records()->update(['status' => 'approved']);

        return redirect()->route('payroll.show', $period)
            ->with('success', "Payroll batch '{$period->title}' approved by " . (Auth::user()?->name ?? 'Authorized Officer'));
    }

    public function disburse(PayrollPeriod $period): RedirectResponse
    {
        if ($period->status !== 'approved') {
            return back()->withErrors(['status' => 'Payroll must be approved before disbursement.']);
        }

        $period->update(['status' => 'disbursed']);
        $period->records()->update(['status' => 'paid']);

        return redirect()->route('payroll.show', $period)
            ->with('success', "Payroll batch '{$period->title}' marked as disbursed and wages paid.");
    }

    public function payslip(PayrollPeriod $period, PayrollRecord $record): View
    {
        $record->load(['employee.assignedSite', 'period']);

        return view('payroll.payslip', compact('period', 'record'));
    }
}
