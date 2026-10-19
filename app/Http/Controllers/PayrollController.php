<?php

namespace App\Http\Controllers;

use App\Events\PayrollStatusUpdated;
use App\Models\Account;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PayrollController extends Controller
{
    protected function getCompanyId(): int
    {
        return (int) (session('current_company_id')
            ?? Auth::user()?->currentCompany()?->id
            ?? Company::configuredCompanyId()
            ?? Company::first()?->id
            ?? 1);
    }

    protected function authorizePayrollView(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('payroll.*') && ! $user->hasPermission('payroll.view') && ! in_array($roleSlug, ['owner', 'admin', 'hr_officer', 'accountant']))) {
            abort(403, 'Unauthorized access to payroll and compensation records.');
        }
    }

    protected function authorizePayrollManage(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('payroll.*') && ! in_array($roleSlug, ['owner', 'admin', 'hr_officer']))) {
            abort(403, 'Unauthorized. Only HR Officers or Administrators can generate or modify payroll batches.');
        }
    }

    protected function authorizePayrollApprove(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('payroll.*') && ! $user->hasPermission('payroll.approve') && ! in_array($roleSlug, ['owner', 'admin', 'hr_officer']))) {
            abort(403, 'Unauthorized to approve payroll batches.');
        }
    }

    protected function authorizePayrollDisburse(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('payroll.*') && ! $user->hasPermission('payroll.disburse') && ! in_array($roleSlug, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized to disburse payroll. Finance authorization required.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizePayrollView();
        $companyId = $this->getCompanyId();

        $periods = PayrollPeriod::where('company_id', $companyId)
            ->with(['approver', 'records.employee'])
            ->latest('start_date')
            ->paginate(10);

        $stats = [
            'total_disbursed' => PayrollPeriod::where('company_id', $companyId)->whereIn('status', ['approved', 'disbursed'])->sum('total_disbursement'),
            'pending_approval' => PayrollPeriod::where('company_id', $companyId)->whereIn('status', ['draft', 'processing'])->count(),
            'active_employees' => Employee::where('company_id', $companyId)->where('status', 'active')->count(),
            'latest_period' => PayrollPeriod::where('company_id', $companyId)->latest('end_date')->first(),
        ];

        return view('payroll.index', compact('periods', 'stats'));
    }

    public function create(): View
    {
        $this->authorizePayrollManage();

        $defaultStart = Carbon::now()->startOfMonth()->toDateString();
        $defaultEnd = Carbon::now()->endOfMonth()->toDateString();
        $defaultTitle = Carbon::now()->format('F Y') . ' Muster Payroll';

        return view('payroll.create', compact('defaultStart', 'defaultEnd', 'defaultTitle'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePayrollManage();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $companyId = $this->getCompanyId();

        $exists = PayrollPeriod::where('company_id', $companyId)
            ->whereDate('start_date', $validated['start_date'])
            ->whereDate('end_date', $validated['end_date'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'start_date' => 'A payroll period covering these exact dates already exists for this company.',
            ]);
        }

        $activeEmployees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->get();

        if ($activeEmployees->isEmpty()) {
            return back()->withInput()->withErrors([
                'title' => 'No active employees found to generate payroll.',
            ]);
        }

        $period = DB::transaction(function () use ($validated, $activeEmployees, $companyId) {
            $period = PayrollPeriod::create([
                'company_id' => $companyId,
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_disbursement' => 0.00,
                'status' => 'processing',
            ]);

            $totalDisbursement = 0.00;

            foreach ($activeEmployees as $emp) {
                // Fetch daily attendance records in this period (only approved attendance counts)
                $attendance = DailyAttendance::where('employee_id', $emp->id)
                    ->whereBetween('attendance_date', [$validated['start_date'], $validated['end_date']])
                    ->where('approval_status', 'approved')
                    ->get();

                $presentCount = $attendance->where('status', 'present')->count();
                $halfDayCount = $attendance->where('status', 'half_day')->count();
                $absentCount = $attendance->where('status', 'absent')->count();

                $daysWorked = $presentCount + ($halfDayCount * 0.5);
                $overtimeHours = (float) $attendance->sum('overtime_hours');

                // Determine base compensation and rates from Salary Structure if configured
                $salaryStructure = $emp->salaryStructures()
                    ->where('effective_from', '<=', $validated['end_date'])
                    ->first();

                $basicPay = 0.00;
                $hourlyOtRate = 0.00;
                $allowances = 0.00;
                $deductions = 0.00;

                if ($salaryStructure) {
                    if ($emp->employment_type === 'daily_wage' || $emp->employment_type === 'machine_operator') {
                        $dailyRate = (float) ($salaryStructure->daily_wage_rate ?: ($emp->daily_wage_rate ?: ($emp->monthly_salary / 26)));
                        $basicPay = $daysWorked * $dailyRate;
                        $hourlyOtRate = (float) ($salaryStructure->hourly_overtime_rate > 0
                            ? $salaryStructure->hourly_overtime_rate
                            : (($dailyRate / 8) * 1.5));
                    } else {
                        $monthlySalary = (float) ($salaryStructure->monthly_salary ?: $emp->monthly_salary);
                        $basicPay = ($daysWorked >= 26) ? $monthlySalary : round(($daysWorked / 26) * $monthlySalary, 2);
                        $hourlyOtRate = (float) ($salaryStructure->hourly_overtime_rate > 0
                            ? $salaryStructure->hourly_overtime_rate
                            : (($monthlySalary / 208) * 1.5));
                    }
                    $allowances = (float) ($salaryStructure->standard_allowance ?? 0.00);
                    if ($salaryStructure->statutory_deduction_percent > 0) {
                        $deductions = round($basicPay * ((float) $salaryStructure->statutory_deduction_percent / 100), 2);
                    }
                } else {
                    // Fallback to standard employment contract rules
                    if ($emp->employment_type === 'daily_wage' || $emp->employment_type === 'machine_operator') {
                        $dailyRate = (float) ($emp->daily_wage_rate ?: ($emp->monthly_salary / 26));
                        $basicPay = $daysWorked * $dailyRate;
                        $hourlyOtRate = ($dailyRate / 8) * 1.5;
                        $allowances = 0.00;
                        $deductions = 0.00;
                    } else {
                        $monthlySalary = (float) $emp->monthly_salary;
                        $basicPay = ($daysWorked >= 26) ? $monthlySalary : round(($daysWorked / 26) * $monthlySalary, 2);
                        $hourlyOtRate = ($monthlySalary / 208) * 1.5;
                        $allowances = ($daysWorked >= 10 && $emp->employment_type !== 'daily_wage') ? 1500.00 : 0.00;
                        $deductions = ($emp->employment_type === 'permanent') ? round($basicPay * 0.05, 2) : 0.00;
                    }
                }

                $overtimePay = round($overtimeHours * $hourlyOtRate, 2);
                $netPay = round(($basicPay + $allowances + $overtimePay) - $deductions, 2);
                $totalDisbursement += $netPay;

                PayrollRecord::create([
                    'company_id' => $companyId,
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

        AppNotification::create([
            'company_id' => $period->company_id,
            'user_id' => null,
            'title' => 'Payroll Batch Submitted for Review',
            'message' => "Payroll period '{$period->title}' submitted for authorized review (" . count($activeEmployees) . " employees).",
            'type' => 'info',
            'action_url' => route('payroll.show', $period),
            'is_read' => false,
        ]);

        return redirect()->route('payroll.show', $period)
            ->with('success', "Payroll batch '{$period->title}' calculated successfully for " . count($activeEmployees) . " employees.");
    }

    public function show(PayrollPeriod $period): View
    {
        $this->authorizePayrollView();

        $period->load(['approver', 'records.employee.assignedSite']);

        return view('payroll.show', compact('period'));
    }

    public function approve(Request $request, PayrollPeriod $period): JsonResponse|RedirectResponse
    {
        $this->authorizePayrollApprove();

        if ($period->status === 'disbursed') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Disbursed payroll periods cannot be modified.'], 422);
            }
            return back()->withErrors(['status' => 'Disbursed payroll periods cannot be modified.']);
        }

        $period->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        $period->records()->update(['status' => 'approved']);

        $periodTitle = $period->title ?? 'Payroll Batch';

        AppNotification::create([
            'company_id' => $period->company_id,
            'user_id' => null,
            'title' => 'Payroll Batch Approved',
            'message' => "Payroll batch '{$periodTitle}' has been approved.",
            'type' => 'success',
            'action_url' => route('payroll.show', $period),
            'is_read' => false,
        ]);

        try {
            broadcast(new PayrollStatusUpdated($period));
        } catch (\Throwable $e) {}

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Payroll batch '{$periodTitle}' approved successfully.",
                'status' => 'approved',
            ]);
        }

        return redirect()->route('payroll.show', $period)
            ->with('success', "Payroll batch '{$periodTitle}' approved by " . (Auth::user()?->name ?? 'Authorized Officer'));
    }

    public function disburse(Request $request, PayrollPeriod $period): JsonResponse|RedirectResponse
    {
        $this->authorizePayrollDisburse();

        if ($period->status !== 'approved') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Payroll must be approved before disbursement.'], 422);
            }
            return back()->withErrors(['status' => 'Payroll must be approved before disbursement.']);
        }

        DB::transaction(function () use ($period) {
            $lockedPeriod = PayrollPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->status === 'disbursed') {
                return;
            }

            $lockedPeriod->update(['status' => 'disbursed']);
            $lockedPeriod->records()->update(['status' => 'paid']);

            // Post GL Journal Entry for Direct Site Labor & Staff Wages
            $companyId = $lockedPeriod->company_id ?? $this->getCompanyId();
            $laborExpenseAccount = Account::where('company_id', $companyId)->where('code', '5010')->first();
            $bankAccount = Account::where('company_id', $companyId)->where('code', '1010')->first();

            if ($laborExpenseAccount && $bankAccount && $lockedPeriod->total_disbursement > 0) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => Carbon::now()->toDateString(),
                    'narration' => "Disbursement of payroll batch: {$lockedPeriod->title}",
                    'total_amount' => $lockedPeriod->total_disbursement,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit Direct Site Labor & Staff Wages
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $laborExpenseAccount->id,
                    'debit' => $lockedPeriod->total_disbursement,
                    'credit' => 0.00,
                    'description' => "Direct labor wages disbursement for {$lockedPeriod->title}",
                ]);

                // Credit Bank Account
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $bankAccount->id,
                    'debit' => 0.00,
                    'credit' => $lockedPeriod->total_disbursement,
                    'description' => "Bank payment for payroll batch {$lockedPeriod->title}",
                ]);

                JournalEntry::lockAfterCreation($journalEntry->id);

                AuditLog::create([
                    'company_id' => $companyId,
                    'user_id' => Auth::id(),
                    'action' => 'payroll_disbursement_posted',
                    'auditable_type' => PayrollPeriod::class,
                    'auditable_id' => $lockedPeriod->id,
                    'new_values' => [
                        'payroll_period_id' => $lockedPeriod->id,
                        'journal_entry_id' => $journalEntry->id,
                        'entry_number' => $journalEntry->entry_number,
                        'amount' => $lockedPeriod->total_disbursement,
                    ],
                ]);
            }
        });

        try {
            broadcast(new PayrollStatusUpdated($period));
        } catch (\Throwable $e) {}

        $periodTitle = $period->title ?? 'Payroll Batch';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Payroll batch '{$periodTitle}' marked as disbursed and wages paid.",
                'status' => 'disbursed',
            ]);
        }

        return redirect()->route('payroll.show', $period)
            ->with('success', "Payroll batch '{$periodTitle}' marked as disbursed and wages paid.");
    }

    public function payslip(PayrollPeriod $period, PayrollRecord $record): View
    {
        $this->authorizePayrollView();

        $record->load(['employee.assignedSite', 'period']);

        return view('payroll.payslip', compact('period', 'record'));
    }
}
