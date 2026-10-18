<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementApprovalPolicy extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'approval_level' => 'integer',
        'requires_owner' => 'boolean',
        'over_budget_requires_owner' => 'boolean',
        'prevent_self_approval' => 'boolean',
        'urgent_escalation' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseRequestApproval::class, 'policy_id');
    }

    public function isApplicable(float $amount, bool $isOverBudget = false, string $priority = 'normal'): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check if amount is within min_amount and max_amount
        if ($amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > (float) $this->max_amount) {
            return false;
        }

        return true;
    }

    public function allowsUserToApprove(User $user, PurchaseRequest $request): array
    {
        $role = $user->currentRole();
        $isOwnerOrAdmin = $user->hasPermission('*') || in_array($role?->slug, ['owner', 'admin']);

        // Check segregation of duties
        if ($this->prevent_self_approval && $request->requested_by === $user->id && ! $isOwnerOrAdmin) {
            return [
                'allowed' => false,
                'reason' => 'Segregation of duties policy prohibits requesters from approving their own requisitions.',
            ];
        }

        // Check if over-budget requires owner/admin
        if ($request->is_over_budget && $this->over_budget_requires_owner && ! $isOwnerOrAdmin) {
            return [
                'allowed' => false,
                'reason' => 'This purchase request exceeds project budget. Company policy mandates Company Owner/Admin approval.',
            ];
        }

        // Check Owner requirement
        if ($this->requires_owner && ! $isOwnerOrAdmin) {
            return [
                'allowed' => false,
                'reason' => 'This approval tier requires Company Owner or Executive Director sign-off.',
            ];
        }

        // Check role requirement
        if ($this->approver_role_id) {
            if ($role?->id !== $this->approver_role_id && ! $isOwnerOrAdmin) {
                return [
                    'allowed' => false,
                    'reason' => "This tier requires the '{$this->role?->name}' role.",
                ];
            }
        } elseif ($this->approver_role_slug) {
            if ($role?->slug !== $this->approver_role_slug && ! $isOwnerOrAdmin) {
                // If role slug is project_manager, also verify manager_id matches the project
                return [
                    'allowed' => false,
                    'reason' => "This tier requires the '{$this->approver_role_slug}' role.",
                ];
            }
        }

        // If user is project manager, ensure they are the PM for this specific project (unless owner/admin)
        if ($role?->slug === 'project_manager' && ! $isOwnerOrAdmin) {
            if ($request->project && $request->project->manager_id && $request->project->manager_id !== $user->id) {
                return [
                    'allowed' => false,
                    'reason' => 'You are not the designated Project Manager for this project.',
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }
}
