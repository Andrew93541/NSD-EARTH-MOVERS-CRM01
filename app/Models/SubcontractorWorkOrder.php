<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubcontractorWorkOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'completion_date' => 'date',
        'contract_value' => 'decimal:2',
        'revised_value' => 'decimal:2',
        'retention_percent' => 'decimal:2',
        'retention_cap_amount' => 'decimal:2',
        'tds_rate_percent' => 'decimal:2',
        'gst_rate_percent' => 'decimal:2',
        'submitted_at' => 'datetime',
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

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'subcontractor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubcontractorWorkOrderItem::class, 'work_order_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(SubcontractorWorkOrderVariation::class, 'work_order_id');
    }

    public function measurementBookEntries(): HasMany
    {
        return $this->hasMany(MeasurementBookEntry::class, 'work_order_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SubcontractorBill::class, 'work_order_id');
    }

    public function effectiveValue(): float
    {
        return (float) ($this->revised_value > 0 ? $this->revised_value : $this->contract_value);
    }

    public function totalCertifiedValue(): float
    {
        return (float) $this->bills()
            ->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])
            ->sum('gross_work_value');
    }

    public function unbilledCommitment(): float
    {
        return max(0.0, $this->effectiveValue() - $this->totalCertifiedValue());
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800 border-gray-200',
            'submitted' => 'bg-amber-100 text-amber-800 border-amber-200',
            'approved', 'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'rejected' => 'bg-red-100 text-red-800 border-red-200',
            'completed' => 'bg-blue-100 text-blue-800 border-blue-200',
            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }
}
