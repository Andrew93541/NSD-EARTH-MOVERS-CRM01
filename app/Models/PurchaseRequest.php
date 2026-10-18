<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'pr_number',
        'project_id',
        'site_id',
        'warehouse_id',
        'request_date',
        'submitted_at',
        'required_by_date',
        'status',
        'priority',
        'reason',
        'estimated_total',
        'is_pricing_complete',
        'is_over_budget',
        'budget_overrun_amount',
        'current_approval_level',
        'required_approval_levels',
        'budget_override_approved',
        'budget_override_reason',
        'rejection_reason',
        'requested_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'submitted_at' => 'datetime',
        'required_by_date' => 'date',
        'approved_at' => 'datetime',
        'estimated_total' => 'decimal:2',
        'budget_overrun_amount' => 'decimal:2',
        'is_pricing_complete' => 'boolean',
        'is_over_budget' => 'boolean',
        'budget_override_approved' => 'boolean',
        'current_approval_level' => 'integer',
        'required_approval_levels' => 'integer',
    ];

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseRequestApproval::class)->orderBy('approval_level');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function totalEstimatedCost(): float
    {
        return (float) $this->items->sum(function ($item) {
            return (float) $item->quantity_requested * (float) $item->estimated_rate;
        });
    }

    public function remainingItemsCount(): int
    {
        return $this->items->filter(function ($item) {
            return $item->remainingQuantity() > 0.001;
        })->count();
    }

    public function recalculateEstimatedTotalAndBudgetImpact(): void
    {
        $this->loadMissing(['items.material', 'project']);

        $total = 0.0;
        $isPricingComplete = true;

        foreach ($this->items as $item) {
            $qty = (float) $item->quantity_requested;
            $rate = (float) $item->estimated_rate;
            if ($rate <= 0.00) {
                // Check if material has standard_cost
                if ($item->material && (float) $item->material->standard_cost > 0) {
                    $rate = (float) $item->material->standard_cost;
                } else {
                    $isPricingComplete = false;
                }
            }
            $total += ($qty * $rate);
        }

        $budgetEval = \App\Services\ProjectBudgetService::evaluateProjectBudget(
            $this->project,
            $total,
            $this->id
        );

        $this->estimated_total = $total;
        $this->is_pricing_complete = $isPricingComplete;
        $this->is_over_budget = $budgetEval['is_over_budget'];
        $this->budget_overrun_amount = $budgetEval['overrun_amount'];

        $tierEval = \App\Services\ApprovalPolicyService::evaluateRequiredTiers($this);
        $this->required_approval_levels = $tierEval['required_levels'];

        $this->save();
    }
}
