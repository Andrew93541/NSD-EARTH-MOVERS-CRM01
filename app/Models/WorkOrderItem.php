<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_rate' => 'decimal:2',
        'total_price' => 'decimal:2',
        'item_number' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if ($item->quantity !== null && $item->unit_rate !== null) {
                $item->total_price = round((float) $item->quantity * (float) $item->unit_rate, 2);
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workCategory(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkCategory::class, 'work_category_id');
    }
}
