<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIssueItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'requested_quantity' => 'decimal:3',
        'issued_quantity' => 'decimal:3',
        'returned_quantity' => 'decimal:3',
        'unit_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function remainingReturnableQuantity(): float
    {
        return max(0.0, round((float) $this->issued_quantity - (float) ($this->returned_quantity ?? 0.0), 3));
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
