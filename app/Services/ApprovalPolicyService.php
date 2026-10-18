<?php

namespace App\Services;

use App\Models\ProcurementApprovalPolicy;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestApproval;
use App\Models\User;
use Carbon\Carbon;

class ApprovalPolicyService
{
    /**
     * Determine required approval tiers and policy metadata for a Purchase Request.
     */
    public static function evaluateRequiredTiers(PurchaseRequest $request): array
    {
        $companyId = $request->company_id ?? session('current_company_id');
        $estimatedTotal = (float) $request->estimated_total;
        $isOverBudget = (bool) $request->is_over_budget;
        $isPricingComplete = (bool) $request->is_pricing_complete;
        $priority = $request->priority ?? 'normal';

        $policies = ProcurementApprovalPolicy::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('approval_level')
            ->get();

        if ($policies->isEmpty()) {
            // Default built-in 2-tier matrix when no custom company policies configured
            $maxLevel = 1;
            if ($estimatedTotal > 100000.00 || $isOverBudget || ! $isPricingComplete || $priority === 'critical') {
                $maxLevel = 2;
            }

            return [
                'has_custom_policy' => false,
                'required_levels' => $maxLevel,
                'policies' => collect(),
                'over_budget_requires_owner' => true,
                'unknown_price_escalated' => (! $isPricingComplete),
            ];
        }

        // Evaluate applicable tiers from custom policies
        $applicablePolicies = collect();
        $highestLevel = 1;

        foreach ($policies as $policy) {
            // If over budget and policy enforces owner over-budget rule
            if ($isOverBudget && $policy->over_budget_requires_owner) {
                $applicablePolicies->push($policy);
                $highestLevel = max($highestLevel, (int) $policy->approval_level);
                continue;
            }

            // If pricing incomplete and policy escalates
            if (! $isPricingComplete && $policy->unknown_price_rule === 'escalate_to_owner') {
                $applicablePolicies->push($policy);
                $highestLevel = max($highestLevel, (int) $policy->approval_level);
                continue;
            }

            // Standard tier threshold check
            if ($estimatedTotal >= (float) $policy->min_amount) {
                $applicablePolicies->push($policy);
                $highestLevel = max($highestLevel, (int) $policy->approval_level);
            }
        }

        return [
            'has_custom_policy' => true,
            'required_levels' => max(1, $highestLevel),
            'policies' => $applicablePolicies->unique('id'),
            'over_budget_requires_owner' => $policies->contains('over_budget_requires_owner', true),
            'unknown_price_escalated' => (! $isPricingComplete && $policies->contains('unknown_price_rule', 'escalate_to_owner')),
        ];
    }

    /**
     * Get the policy governing the current approval level of a request.
     */
    public static function getCurrentLevelPolicy(PurchaseRequest $request): ?ProcurementApprovalPolicy
    {
        return ProcurementApprovalPolicy::where('company_id', $request->company_id)
            ->where('is_active', true)
            ->where('approval_level', $request->current_approval_level)
            ->first();
    }

    /**
     * Check whether a specific user is authorized to approve at the current pending level.
     */
    public static function canUserApprove(User $user, PurchaseRequest $request): array
    {
        if ($request->status !== 'pending') {
            return [
                'can_approve' => false,
                'reason' => 'Only pending requests can be approved.',
            ];
        }

        $role = $user->currentRole();
        $roleSlug = $role?->slug;
        $isOwnerOrAdmin = $user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin']);
        $policy = self::getCurrentLevelPolicy($request);

        // 1. Role Authority check for this level
        $hasRoleAuthority = false;
        if ($isOwnerOrAdmin) {
            $hasRoleAuthority = true;
        } elseif ($policy) {
            if ($policy->requires_owner) {
                $hasRoleAuthority = false;
            } elseif ($policy->approver_role_id && $role?->id === $policy->approver_role_id) {
                $hasRoleAuthority = true;
            } elseif ($policy->approver_role_slug && $roleSlug === $policy->approver_role_slug) {
                $hasRoleAuthority = true;
            } elseif ($policy->approval_level === 1 && $roleSlug === 'project_manager' && ($request->project?->manager_id === null || $request->project?->manager_id === $user->id)) {
                $hasRoleAuthority = true;
            }
        } else {
            // Default built-in tiers
            if ($request->current_approval_level === 1) {
                $hasRoleAuthority = ($roleSlug === 'project_manager' && ($request->project?->manager_id === null || $request->project?->manager_id === $user->id));
            } elseif ($request->current_approval_level >= 2) {
                $hasRoleAuthority = ($roleSlug === 'accountant');
            }
        }

        if (! $hasRoleAuthority) {
            return [
                'can_approve' => false,
                'is_unauthorized_role' => true,
                'reason' => 'You do not have authorization to approve purchase requests at this level.',
            ];
        }

        // 2. Segregation of duties: Requesters cannot self-approve unless company owner/admin
        $preventSelfApproval = $policy ? $policy->prevent_self_approval : true;
        if ($preventSelfApproval && $request->requested_by === $user->id && ! $isOwnerOrAdmin) {
            return [
                'can_approve' => false,
                'is_unauthorized_role' => false,
                'reason' => 'Segregation of duties: You cannot approve your own purchase request.',
            ];
        }

        // 3. Over-budget rule: Requires Owner or Admin if over-budget
        if ($request->is_over_budget) {
            $requiresOwner = $policy ? $policy->over_budget_requires_owner : true;
            if ($requiresOwner && ! $isOwnerOrAdmin) {
                return [
                    'can_approve' => false,
                    'is_unauthorized_role' => false,
                    'reason' => 'This purchase request exceeds project budget. Company policy mandates Company Owner/Admin approval.',
                ];
            }
        }

        return [
            'can_approve' => true,
            'is_unauthorized_role' => false,
            'reason' => null,
        ];
    }

    /**
     * Process and record an approval decision for the current level.
     */
    public static function recordApproval(
        PurchaseRequest $request,
        User $user,
        ?string $comments = null,
        bool $isBudgetOverride = false,
        ?string $overrideReason = null
    ): array {
        $check = self::canUserApprove($user, $request);
        if (! $check['can_approve']) {
            return [
                'success' => false,
                'error' => $check['reason'],
            ];
        }

        // Over-budget requires override reason
        if ($request->is_over_budget && empty($overrideReason)) {
            return [
                'success' => false,
                'error' => 'An override reason is mandatory when approving an over-budget purchase request.',
            ];
        }

        $policy = self::getCurrentLevelPolicy($request);
        $currentLevel = $request->current_approval_level;
        $requiredLevels = $request->required_approval_levels;

        // Record approval log
        PurchaseRequestApproval::create([
            'company_id' => $request->company_id,
            'purchase_request_id' => $request->id,
            'policy_id' => $policy?->id,
            'approval_level' => $currentLevel,
            'approver_id' => $user->id,
            'action' => 'approved',
            'comments' => $comments,
            'is_budget_override' => $isBudgetOverride || $request->is_over_budget,
            'override_reason' => $overrideReason,
            'snapshot_estimated_total' => $request->estimated_total,
            'snapshot_remaining_budget' => ProjectBudgetService::evaluateProjectBudget($request->project, 0, $request->id)['remaining_before'],
        ]);

        $isOwnerOrAdmin = $user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin']);
        $isFinalLevel = ($currentLevel >= $requiredLevels) || ($isOwnerOrAdmin && ($isBudgetOverride || $request->is_over_budget));

        if ($isFinalLevel) {
            $request->update([
                'status' => 'approved',
                'current_approval_level' => $requiredLevels,
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
                'budget_override_approved' => ($isBudgetOverride || $request->is_over_budget),
                'budget_override_reason' => $overrideReason,
            ]);

            return [
                'success' => true,
                'is_final' => true,
                'message' => "Purchase Request {$request->pr_number} fully approved and queued for PO conversion.",
            ];
        } else {
            $nextLevel = $currentLevel + 1;
            $request->update([
                'current_approval_level' => $nextLevel,
            ]);

            return [
                'success' => true,
                'is_final' => false,
                'message' => "Level {$currentLevel} approval recorded. Requisition is now pending Level {$nextLevel} authorization.",
            ];
        }
    }
}
