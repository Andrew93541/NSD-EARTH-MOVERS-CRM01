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
    ];

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
}
