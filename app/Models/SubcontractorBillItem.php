<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractorBillItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'previously_billed_quantity' => 'decimal:3',
        'current_billed_quantity' => 'decimal:3',
        'cumulative_billed_quantity' => 'decimal:3',
        'unit_rate' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SubcontractorBill::class, 'subcontractor_bill_id');
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrderItem::class, 'work_order_item_id');
    }
}
