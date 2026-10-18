<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'purchase_date' => 'date',
        'capitalization_date' => 'date',
        'depreciation_start_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'eligible_capitalized_costs' => 'decimal:2',
        'total_capitalized_cost' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'depreciation_rate_percent' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'current_book_value' => 'decimal:2',
        'disposal_proceeds' => 'decimal:2',
    ];

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function accumulatedDeprAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depr_account_id');
    }

    public function deprExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depr_expense_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(AssetDepreciationEntry::class)->orderBy('period_end_date', 'desc');
    }

    public function depreciableAmount(): float
    {
        return max(0.0, round((float) $this->total_capitalized_cost - (float) $this->residual_value, 2));
    }

    public function monthlyStraightLineDepreciation(): float
    {
        $months = max(1, (int) $this->useful_life_months);
        return round($this->depreciableAmount() / $months, 2);
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'under_maintenance' => 'bg-amber-50 text-amber-700 border-amber-200',
            'idle' => 'bg-blue-50 text-blue-700 border-blue-200',
            'disposed' => 'bg-purple-50 text-purple-700 border-purple-200',
            'retired' => 'bg-gray-100 text-gray-700 border-gray-200',
            default => 'bg-gray-50 text-gray-600 border-gray-200',
        };
    }
}
