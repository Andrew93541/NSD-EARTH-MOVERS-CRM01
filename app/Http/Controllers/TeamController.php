<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! in_array($roleSlug, ['owner', 'admin']))) {
            abort(403, 'Unauthorized. Team and access control management is restricted to Administrators and Company Owners.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $users = User::whereHas('companies', function ($q) use ($companyId) {
            $q->where('companies.id', $companyId);
        })
        ->with(['companies' => function ($q) use ($companyId) {
            $q->where('companies.id', $companyId);
        }, 'employee'])
        ->paginate(15);

        $roles = Role::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->get();

        $stats = [
            'total_users' => $users->total(),
            'total_roles' => $roles->count(),
            'active_owners' => DB::table('company_user')
                ->join('roles', 'company_user.role_id', '=', 'roles.id')
                ->where('company_user.company_id', $companyId)
                ->where('company_user.status', 'active')
                ->where('roles.slug', 'owner')
                ->count(),
        ];

        return view('team.index', compact('users', 'roles', 'stats'));
    }

    public function roles(Request $request): View
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $roles = Role::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->withCount('users')->get();

        // Master capability list for permission matrix
        $capabilities = [
            'projects' => ['projects.view', 'projects.create', 'projects.edit', 'projects.delete'],
            'sites' => ['sites.view', 'sites.manage', 'daily_logs.create', 'daily_logs.approve'],
            'attendance' => ['attendance.view', 'attendance.record', 'attendance.approve', 'attendance.approve_office'],
            'payroll' => ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.disburse'],
            'procurement' => ['procurement.view', 'materials.request', 'purchase-requests.convert', 'procurement.receive'],
            'billing' => ['billing.view', 'billing.create', 'billing.payments', 'retention.manage'],
            'subcontractors' => ['subcontractors.view', 'subcontractors.manage', 'measurement_books.certify', 'subcontractor_bills.certify'],
            'finance' => ['finance.view', 'accounting.journals', 'loans.manage', 'assets.manage'],
            'administration' => ['team.manage', 'company.settings', 'documents.manage', 'audit.view'],
        ];

        return view('team.roles', compact('roles', 'capabilities'));
    }

    public function storeRole(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'slug')->where('company_id', $companyId),
            ],
            'description' => 'nullable|string|max:500',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $validated['company_id'] = $companyId;
        $validated['is_system'] = false;

        $role = Role::create($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'action' => 'create_role',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'new_values' => $role->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'role' => $role], 201);
        }

        return back()->with('success', "Custom role '{$role->name}' created successfully.");
    }

    public function updateRole(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($role->company_id && $role->company_id !== $companyId) {
            abort(403, 'Unauthorized company role.');
        }

        // System role protection: Owner role permissions cannot be stripped
        if ($role->slug === 'owner') {
            abort(403, 'The Company Owner role permissions are immutable and require wildcard (*) access.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $oldValues = $role->toArray();
        $role->update($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'action' => 'update_role',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'old_values' => $oldValues,
            'new_values' => $role->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'role' => $role]);
        }

        return back()->with('success', "Role '{$role->name}' updated successfully.");
    }

    public function assignUserRole(Request $request, User $targetUser): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;
        $currentUser = Auth::user();
        $currentRoleSlug = $currentUser?->currentRole()?->slug;

        $validated = $request->validate([
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                }),
            ],
            'status' => 'required|in:active,inactive',
        ]);

        $newRole = Role::findOrFail($validated['role_id']);

        // Security Invariant 1: Prevent self-privilege escalation
        if ((int) $targetUser->id === (int) $currentUser->id && $currentRoleSlug !== 'owner') {
            abort(403, 'Self-privilege escalation blocked: You cannot modify your own assigned role.');
        }

        // Security Invariant 2: Prevent accidental removal/demotion/deactivation of the LAST Company Owner
        $currentPivot = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $targetUser->id)
            ->first();

        $targetUserRole = $currentPivot ? Role::find($currentPivot->role_id) : null;
        $isCurrentlyOwner = ($targetUserRole?->slug === 'owner') && ($currentPivot->status === 'active');

        if ($isCurrentlyOwner && ($newRole->slug !== 'owner' || $validated['status'] !== 'active')) {
            $ownerCount = DB::table('company_user')
                ->join('roles', 'company_user.role_id', '=', 'roles.id')
                ->where('company_user.company_id', $companyId)
                ->where('company_user.status', 'active')
                ->where('roles.slug', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                abort(403, 'Action Denied: Cannot demote or deactivate the last active Company Owner of this organization.');
            }
        }

        DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $targetUser->id)
            ->update([
                'role_id' => $newRole->id,
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $currentUser->id,
            'action' => 'assign_user_role',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'old_values' => [
                'role_id' => $currentPivot?->role_id,
                'status' => $currentPivot?->status,
            ],
            'new_values' => [
                'role_id' => $newRole->id,
                'role_name' => $newRole->name,
                'status' => $validated['status'],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Role '{$newRole->name}' assigned to {$targetUser->name}.",
            ]);
        }

        return back()->with('success', "Role '{$newRole->name}' assigned to {$targetUser->name} successfully.");
    }
}
