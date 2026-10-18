<?php

namespace App\Http\Controllers;

use App\Models\AttendancePunch;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $selectedDate = $request->input('date', Carbon::today()->toDateString());

        $attendance = DailyAttendance::with(['employee', 'site', 'project'])
            ->whereDate('attendance_date', $selectedDate)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'present' => DailyAttendance::whereDate('attendance_date', $selectedDate)->where('status', 'present')->count(),
            'half_day' => DailyAttendance::whereDate('attendance_date', $selectedDate)->where('status', 'half_day')->count(),
            'absent' => DailyAttendance::whereDate('attendance_date', $selectedDate)->where('status', 'absent')->count(),
            'total_workers' => Employee::where('status', 'active')->count(),
        ];

        return view('attendance.index', compact('attendance', 'selectedDate', 'stats'));
    }

    public function musterRoll(Request $request): View
    {
        $sites = Site::orderBy('name')->get();
        $selectedSite = $request->site_id ? Site::find($request->site_id) : $sites->first();
        $selectedDate = $request->input('date', Carbon::today()->toDateString());

        $employees = Employee::where('status', 'active')
            ->when($selectedSite, function ($q) use ($selectedSite) {
                $q->where('assigned_site_id', $selectedSite->id);
            })
            ->get();

        $existingAttendance = DailyAttendance::whereDate('attendance_date', $selectedDate)
            ->get()
            ->keyBy('employee_id');

        return view('attendance.muster-roll', compact('sites', 'selectedSite', 'selectedDate', 'employees', 'existingAttendance'));
    }

    public function storeMuster(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'site_id' => 'required|exists:sites,id',
            'attendance' => 'required|array',
            'attendance.*.status' => 'required|in:present,half_day,absent,paid_leave',
            'attendance.*.overtime_hours' => 'nullable|numeric|min:0|max:12',
        ]);

        $date = $validated['date'];
        $site = Site::findOrFail($validated['site_id']);

        // 1. Scope authorization: verify user is assigned supervisor, PM, or admin
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $isAuthorized = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'project_manager']) || $site->supervisor_id === $user->id;
        if (! $isAuthorized) {
            abort(403, 'You are not authorized to submit muster for this site.');
        }

        // 2. Data integrity: prevent tampering with historical attendance covered by disbursed payroll
        $isPeriodDisbursed = \App\Models\PayrollPeriod::where('status', 'disbursed')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($isPeriodDisbursed) {
            return back()->withInput()->withErrors([
                'date' => "Attendance for {$date} cannot be modified because the corresponding payroll period has already been finalized and disbursed.",
            ]);
        }

        foreach ($validated['attendance'] as $employeeId => $data) {
            DailyAttendance::updateOrCreate(
                [
                    'company_id' => $site->company_id,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                ],
                [
                    'site_id' => $site->id,
                    'project_id' => $site->project_id,
                    'status' => $data['status'],
                    'worked_hours' => $data['status'] === 'present' ? 8.00 : ($data['status'] === 'half_day' ? 4.00 : 0.00),
                    'overtime_hours' => $data['overtime_hours'] ?? 0.00,
                    'is_overtime_approved' => ($data['overtime_hours'] ?? 0) > 0,
                    'overtime_approved_by' => Auth::id(),
                ]
            );
        }

        return redirect()->route('attendance.muster', ['site_id' => $site->id, 'date' => $date])
            ->with('success', 'Muster roll attendance submitted and synchronized with payroll.');
    }
}
