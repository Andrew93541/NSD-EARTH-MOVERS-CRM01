<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'material_id',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'rejection_reason',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'quantity_accepted' => 'decimal:3',
        'quantity_rejected' => 'decimal:3',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
