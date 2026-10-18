<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractorWorkOrderItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'approved_quantity' => 'decimal:3',
        'unit_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'certified_quantity' => 'decimal:3',
        'billed_quantity' => 'decimal:3',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrder::class, 'work_order_id');
    }

    public function measurementBookEntries(): HasMany
    {
        return $this->hasMany(MeasurementBookEntry::class, 'work_order_item_id');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(SubcontractorBillItem::class, 'work_order_item_id');
    }

    public function remainingQuantity(): float
    {
        return max(0.0, (float) $this->approved_quantity - (float) $this->billed_quantity);
    }

    public function eligibleApprovedUnbilledQuantity(): float
    {
        $approvedMeasured = (float) $this->measurementBookEntries()
            ->where('status', 'approved')
            ->sum('measured_quantity');

        return max(0.0, $approvedMeasured);
    }
}
