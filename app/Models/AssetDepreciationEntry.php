<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'opening_book_value' => 'decimal:2',
        'depreciation_amount' => 'decimal:2',
        'closing_book_value' => 'decimal:2',
        'accumulated_depreciation_after' => 'decimal:2',
        'is_partial_period' => 'boolean',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'posted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'approved' => 'bg-blue-50 text-blue-700 border-blue-200',
            'calculated' => 'bg-amber-50 text-amber-700 border-amber-200',
            'reversed' => 'bg-red-50 text-red-700 border-red-200',
            default => 'bg-gray-50 text-gray-600 border-gray-200',
        };
    }
}
