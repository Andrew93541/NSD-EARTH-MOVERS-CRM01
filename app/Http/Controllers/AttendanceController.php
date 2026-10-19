<?php

namespace App\Http\Controllers;

use App\Events\AttendanceUpdated;
use App\Models\AppNotification;
use App\Models\AttendancePunch;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\Site;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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

    public function storeMuster(Request $request): JsonResponse|RedirectResponse
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
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "Attendance for {$date} cannot be modified because the corresponding payroll period has already been finalized and disbursed.",
                ], 422);
            }
            return back()->withInput()->withErrors([
                'date' => "Attendance for {$date} cannot be modified because the corresponding payroll period has already been finalized and disbursed.",
            ]);
        }

        $date = Carbon::parse($validated['date'])->toDateString();

        foreach ($validated['attendance'] as $employeeId => $data) {
            $existing = DailyAttendance::where('company_id', $site->company_id)
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $date)
                ->first();

            // Guard: approved attendance cannot be ordinarily edited
            if ($existing && $existing->approval_status === 'approved') {
                $hasChanged = ($existing->status !== $data['status']) ||
                    (abs((float)$existing->overtime_hours - (float)($data['overtime_hours'] ?? 0)) > 0.01);

                if ($hasChanged) {
                    $empName = $existing->employee?->full_name ?: "Worker #{$employeeId}";
                    $msg = "Attendance for {$empName} on {$date} has already been approved and cannot be ordinarily modified. Please submit an attendance correction request.";
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }
                    return back()->withInput()->withErrors(['attendance' => $msg]);
                }
            }

            $workflowAction = $request->input('action', 'approve');
            $statusToSet = 'approved';
            $approvedBy = Auth::id();
            $approvedAt = now();
            $submittedAt = now();

            if ($workflowAction === 'save_draft') {
                $statusToSet = 'draft';
                $approvedBy = null;
                $approvedAt = null;
            } elseif ($workflowAction === 'submit') {
                $statusToSet = 'pending_approval';
                $approvedBy = null;
                $approvedAt = null;
            }

            $recordAttributes = [
                'site_id' => $site->id,
                'project_id' => $site->project_id,
                'status' => $data['status'],
                'worked_hours' => $data['status'] === 'present' ? 8.00 : ($data['status'] === 'half_day' ? 4.00 : 0.00),
                'overtime_hours' => $data['overtime_hours'] ?? 0.00,
                'is_overtime_approved' => ($data['overtime_hours'] ?? 0) > 0,
                'overtime_approved_by' => Auth::id(),
                'approval_status' => $statusToSet,
                'submitted_at' => $submittedAt,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
            ];

            if ($existing) {
                $existing->update($recordAttributes);
            } else {
                DailyAttendance::create(array_merge($recordAttributes, [
                    'company_id' => $site->company_id,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                ]));
            }
        }

        $pmId = $site->project?->manager_id;
        if ($pmId && (int) $pmId !== (int) Auth::id()) {
            $notification = AppNotification::create([
                'company_id' => $site->company_id,
                'user_id' => $pmId,
                'title' => 'Muster Roll Submitted',
                'message' => "Daily attendance for {$site->name} on " . Carbon::parse($date)->format('d M Y') . " submitted by " . Auth::user()->name . ".",
                'type' => 'info',
                'action_url' => route('attendance.muster', ['site_id' => $site->id, 'date' => $date]),
                'is_read' => false,
            ]);
            NotificationService::broadcastNotification($notification);
        }

        try {
            broadcast(new AttendanceUpdated($site->company_id, $site->id, $site->name, $date, count($validated['attendance'])));
        } catch (\Throwable $e) {
            // Graceful fallback if Reverb is offline
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Muster roll attendance submitted and synchronized with payroll.',
                'date' => $date,
                'site_id' => $site->id,
                'count' => count($validated['attendance']),
            ]);
        }

        return redirect()->route('attendance.muster', ['site_id' => $site->id, 'date' => $date])
            ->with('success', 'Muster roll attendance submitted and synchronized with payroll.');
    }

    public function approve(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'attendance_ids' => 'required|array',
            'attendance_ids.*' => 'exists:daily_attendance,id',
        ]);

        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $approvedCount = 0;

        foreach ($validated['attendance_ids'] as $id) {
            $attendance = DailyAttendance::with(['site', 'employee'])->findOrFail($id);

            // Server-side relationship enforcement:
            // Site Supervisor is primary approver for workers at their authorized site.
            // Office staff without site assignments are approved by HR Officer or Admin.
            if ($attendance->site_id) {
                $isSiteSupervisor = (int) $attendance->site?->supervisor_id === (int) $user->id;
                $isGlobalAuth = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'project_manager']);
                if (! $isSiteSupervisor && ! $isGlobalAuth) {
                    abort(403, "Unauthorized. You are not the designated Site Supervisor for {$attendance->site?->name}.");
                }
            } else {
                // Office staff approval route
                $isHRorAdmin = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'hr_officer']);
                if (! $isHRorAdmin) {
                    abort(403, 'Unauthorized. Office employee attendance must be approved by HR or Admin.');
                }
            }

            $attendance->update([
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);
            $approvedCount++;
        }

        $msg = "{$approvedCount} attendance record(s) approved successfully.";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    public function reject(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'attendance_ids' => 'required|array',
            'attendance_ids.*' => 'exists:daily_attendance,id',
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);

        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $rejectedCount = 0;

        foreach ($validated['attendance_ids'] as $id) {
            $attendance = DailyAttendance::with(['site'])->findOrFail($id);

            if ($attendance->site_id) {
                $isSiteSupervisor = (int) $attendance->site?->supervisor_id === (int) $user->id;
                $isGlobalAuth = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'project_manager']);
                if (! $isSiteSupervisor && ! $isGlobalAuth) {
                    abort(403, "Unauthorized. You are not the designated Site Supervisor for this site.");
                }
            } else {
                $isHRorAdmin = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'hr_officer']);
                if (! $isHRorAdmin) {
                    abort(403, 'Unauthorized. Office employee attendance rejection requires HR or Admin authorization.');
                }
            }

            $attendance->update([
                'approval_status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $rejectedCount++;
        }

        $msg = "{$rejectedCount} attendance record(s) rejected.";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    public function requestCorrection(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'daily_attendance_id' => 'required|exists:daily_attendance,id',
            'proposed_status' => 'required|in:present,half_day,absent,paid_leave',
            'proposed_worked_hours' => 'nullable|numeric|min:0|max:24',
            'proposed_overtime_hours' => 'nullable|numeric|min:0|max:12',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $attendance = DailyAttendance::findOrFail($validated['daily_attendance_id']);

        // Check if covered by disbursed payroll period
        $isPeriodDisbursed = \App\Models\PayrollPeriod::where('status', 'disbursed')
            ->whereDate('start_date', '<=', $attendance->attendance_date)
            ->whereDate('end_date', '>=', $attendance->attendance_date)
            ->exists();

        if ($isPeriodDisbursed) {
            $msg = "Cannot submit correction: the payroll period covering {$attendance->attendance_date} has already been finalized and disbursed.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors(['reason' => $msg]);
        }

        $hours = $validated['proposed_worked_hours'] ?? ($validated['proposed_status'] === 'present' ? 8.00 : ($validated['proposed_status'] === 'half_day' ? 4.00 : 0.00));

        $correction = \App\Models\DailyAttendanceCorrection::create([
            'company_id' => $attendance->company_id,
            'daily_attendance_id' => $attendance->id,
            'requested_by' => Auth::id(),
            'previous_status' => $attendance->status,
            'proposed_status' => $validated['proposed_status'],
            'previous_worked_hours' => $attendance->worked_hours,
            'proposed_worked_hours' => $hours,
            'previous_overtime_hours' => $attendance->overtime_hours,
            'proposed_overtime_hours' => $validated['proposed_overtime_hours'] ?? 0.00,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $msg = 'Attendance correction request submitted for supervisor / HR reapproval.';
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg, 'correction' => $correction]);
        }
        return back()->with('success', $msg);
    }

    public function approveCorrection(Request $request, \App\Models\DailyAttendanceCorrection $correction): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        $attendance = $correction->attendance;
        if ($attendance && $attendance->site_id) {
            $isSupervisor = (int) $attendance->site?->supervisor_id === (int) $user->id;
            $isGlobal = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'project_manager']);
            if (! $isSupervisor && ! $isGlobal) {
                abort(403, 'Unauthorized to approve attendance correction for this site.');
            }
        } else {
            $isHR = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'hr_officer']);
            if (! $isHR) {
                abort(403, 'Unauthorized to approve office attendance correction.');
            }
        }

        // Apply correction to daily attendance record
        $attendance->update([
            'status' => $correction->proposed_status,
            'worked_hours' => $correction->proposed_worked_hours,
            'overtime_hours' => $correction->proposed_overtime_hours,
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $correction->update([
            'status' => 'approved',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $request->input('action_notes', 'Correction approved and applied.'),
        ]);

        $msg = 'Attendance correction approved and applied.';
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    public function rejectCorrection(Request $request, \App\Models\DailyAttendanceCorrection $correction): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        $attendance = $correction->attendance;
        if ($attendance && $attendance->site_id) {
            $isSupervisor = (int) $attendance->site?->supervisor_id === (int) $user->id;
            $isGlobal = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'project_manager']);
            if (! $isSupervisor && ! $isGlobal) {
                abort(403, 'Unauthorized to reject attendance correction for this site.');
            }
        } else {
            $isHR = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'hr_officer']);
            if (! $isHR) {
                abort(403, 'Unauthorized to reject office attendance correction.');
            }
        }

        $correction->update([
            'status' => 'rejected',
            'actioned_by' => $user->id,
            'actioned_at' => now(),
            'action_notes' => $validated['reason'],
        ]);

        $msg = 'Attendance correction request rejected.';
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }
}
