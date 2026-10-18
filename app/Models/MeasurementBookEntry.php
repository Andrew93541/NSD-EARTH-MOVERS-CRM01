<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeasurementBookEntry extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'measurement_date' => 'date',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'length' => 'decimal:3',
        'breadth' => 'decimal:3',
        'depth_height' => 'decimal:3',
        'multiplier_units' => 'decimal:2',
        'manual_quantity_override' => 'decimal:3',
        'measured_quantity' => 'decimal:3',
        'previously_certified_quantity' => 'decimal:3',
        'cumulative_quantity' => 'decimal:3',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrder::class, 'work_order_id');
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrderItem::class, 'work_order_item_id');
    }

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

    public function measuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'measured_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function certifiedInBill(): BelongsTo
    {
        return $this->belongsTo(SubcontractorBill::class, 'certified_in_bill_id');
    }

    public static function computeFormulaQuantity(
        string $formula,
        ?float $l,
        ?float $b,
        ?float $d,
        float $multiplier = 1.00,
        ?float $manualOverride = null
    ): float {
        if ($manualOverride !== null && $manualOverride > 0) {
            return round($manualOverride, 3);
        }

        $multiplier = $multiplier > 0 ? $multiplier : 1.00;

        return match ($formula) {
            'dimensions_l_b_h' => round(((float) $l) * ((float) $b) * ((float) $d) * $multiplier, 3),
            'area_depth_a_d' => round(((float) $l) * ((float) $b) * $multiplier, 3),
            'unit_count_nos' => round(((float) ($l ?: 1.0)) * $multiplier, 3),
            'direct_manual' => round((float) ($manualOverride ?? 0.0), 3),
            default => round(((float) $l) * ((float) $b) * ((float) $d) * $multiplier, 3),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800 border-gray-200',
            'submitted' => 'bg-amber-100 text-amber-800 border-amber-200',
            'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'rejected' => 'bg-red-100 text-red-800 border-red-200',
            'certified' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }
}
