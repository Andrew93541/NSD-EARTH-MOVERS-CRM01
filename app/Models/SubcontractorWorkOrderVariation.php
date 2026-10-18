<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractorWorkOrderVariation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'previous_quantity' => 'decimal:3',
        'revised_quantity' => 'decimal:3',
        'previous_rate' => 'decimal:2',
        'revised_rate' => 'decimal:2',
        'value_impact' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrder::class, 'work_order_id');
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrderItem::class, 'work_order_item_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
            'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'rejected' => 'bg-red-100 text-red-800 border-red-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }
}
