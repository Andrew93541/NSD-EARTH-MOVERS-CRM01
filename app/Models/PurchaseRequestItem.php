<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'material_id',
        'specifications',
        'quantity_requested',
        'quantity_ordered',
        'estimated_rate',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_ordered' => 'decimal:3',
        'estimated_rate' => 'decimal:2',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function remainingQuantity(): float
    {
        return max(0.0, (float) $this->quantity_requested - (float) $this->quantity_ordered);
    }

    public function estimatedTotal(): float
    {
        return round((float) $this->quantity_requested * (float) $this->estimated_rate, 2);
    }
}
