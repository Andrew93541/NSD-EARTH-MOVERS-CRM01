<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ProcurementApprovalPolicy;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProcurementApprovalPolicyController extends Controller
{
    protected function authorizeAdmin(): void
    {
        $user = Auth::user();
        $roleSlug = $user->currentRole()?->slug;
        $isAdminOrOwner = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);

        if (! $isAdminOrOwner) {
            abort(403, 'Unauthorized. Only Company Owners and Administrators can configure procurement approval policies.');
        }
    }

    public function index(): View
    {
        $this->authorizeAdmin();

        $policies = ProcurementApprovalPolicy::with('role')
            ->orderBy('approval_level')
            ->orderBy('min_amount')
            ->get();

        $roles = Role::orderBy('name')->get();

        return view('procurement.policies.index', compact('policies', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
            'approval_level' => 'required|integer|between:1,5',
            'approver_role_id' => 'nullable|exists:roles,id',
            'approver_role_slug' => 'nullable|string|max:50',
            'requires_owner' => 'boolean',
            'over_budget_requires_owner' => 'boolean',
            'unknown_price_rule' => 'required|in:require_pricing,escalate_to_owner,allow_tier1',
            'prevent_self_approval' => 'boolean',
            'urgent_escalation' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        $companyId = Auth::user()->currentCompany()?->id ?? session('current_company_id');

        $policy = ProcurementApprovalPolicy::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'min_amount' => $validated['min_amount'],
            'max_amount' => $validated['max_amount'] ?? null,
            'approval_level' => $validated['approval_level'],
            'approver_role_id' => $validated['approver_role_id'] ?? null,
            'approver_role_slug' => $validated['approver_role_slug'] ?? ($validated['approver_role_id'] ? Role::find($validated['approver_role_id'])?->slug : null),
            'requires_owner' => $request->boolean('requires_owner'),
            'over_budget_requires_owner' => $request->boolean('over_budget_requires_owner', true),
            'unknown_price_rule' => $validated['unknown_price_rule'],
            'prevent_self_approval' => $request->boolean('prevent_self_approval', true),
            'urgent_escalation' => $request->boolean('urgent_escalation'),
            'is_active' => true,
            'description' => $validated['description'] ?? null,
        ]);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'action' => 'approval_policy_created',
            'auditable_type' => ProcurementApprovalPolicy::class,
            'auditable_id' => $policy->id,
            'new_values' => $policy->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('procurement.policies.index')
            ->with('success', "Approval policy tier '{$policy->name}' created successfully.");
    }

    public function update(Request $request, ProcurementApprovalPolicy $policy): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
            'approval_level' => 'required|integer|between:1,5',
            'approver_role_id' => 'nullable|exists:roles,id',
            'approver_role_slug' => 'nullable|string|max:50',
            'requires_owner' => 'boolean',
            'over_budget_requires_owner' => 'boolean',
            'unknown_price_rule' => 'required|in:require_pricing,escalate_to_owner,allow_tier1',
            'prevent_self_approval' => 'boolean',
            'urgent_escalation' => 'boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        $policy->update([
            'name' => $validated['name'],
            'min_amount' => $validated['min_amount'],
            'max_amount' => $validated['max_amount'] ?? null,
            'approval_level' => $validated['approval_level'],
            'approver_role_id' => $validated['approver_role_id'] ?? null,
            'approver_role_slug' => $validated['approver_role_slug'] ?? ($validated['approver_role_id'] ? Role::find($validated['approver_role_id'])?->slug : $policy->approver_role_slug),
            'requires_owner' => $request->boolean('requires_owner'),
            'over_budget_requires_owner' => $request->boolean('over_budget_requires_owner'),
            'unknown_price_rule' => $validated['unknown_price_rule'],
            'prevent_self_approval' => $request->boolean('prevent_self_approval'),
            'urgent_escalation' => $request->boolean('urgent_escalation'),
            'is_active' => $request->boolean('is_active', true),
            'description' => $validated['description'] ?? null,
        ]);

        AuditLog::create([
            'company_id' => $policy->company_id,
            'user_id' => Auth::id(),
            'action' => 'approval_policy_updated',
            'auditable_type' => ProcurementApprovalPolicy::class,
            'auditable_id' => $policy->id,
            'new_values' => $policy->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('procurement.policies.index')
            ->with('success', "Approval policy '{$policy->name}' updated successfully.");
    }

    public function destroy(ProcurementApprovalPolicy $policy): RedirectResponse
    {
        $this->authorizeAdmin();

        $name = $policy->name;
        $policy->delete();

        return redirect()->route('procurement.policies.index')
            ->with('success', "Approval policy tier '{$name}' deleted.");
    }
}
