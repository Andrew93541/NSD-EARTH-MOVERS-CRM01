<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubcontractorBill extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'bill_date' => 'date',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'gross_work_value' => 'decimal:2',
        'cumulative_gross_value' => 'decimal:2',
        'previously_certified_value' => 'decimal:2',
        'retention_rate_percent' => 'decimal:2',
        'retention_amount' => 'decimal:2',
        'tds_rate_percent' => 'decimal:2',
        'tds_amount' => 'decimal:2',
        'gst_rate_percent' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'advance_recovery_amount' => 'decimal:2',
        'other_deductions_amount' => 'decimal:2',
        'net_payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'certified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'subcontractor_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrder::class, 'work_order_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubcontractorBillItem::class, 'subcontractor_bill_id');
    }

    public function measurementsPivot(): HasMany
    {
        return $this->hasMany(SubcontractorBillMeasurement::class, 'subcontractor_bill_id');
    }

    public function measurementBookEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            MeasurementBookEntry::class,
            'subcontractor_bill_measurements',
            'subcontractor_bill_id',
            'measurement_book_entry_id'
        )->withPivot('allocated_quantity');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractorBillPayment::class, 'subcontractor_bill_id');
    }

    public function outstandingBalance(): float
    {
        return max(0.0, round((float) $this->net_payable_amount - (float) $this->paid_amount, 2));
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800 border-gray-200',
            'submitted' => 'bg-amber-100 text-amber-800 border-amber-200',
            'certified' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'approved_for_payment' => 'bg-blue-100 text-blue-800 border-blue-200',
            'partially_paid' => 'bg-teal-100 text-teal-800 border-teal-200',
            'paid' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'rejected' => 'bg-red-100 text-red-800 border-red-200',
            'reversed' => 'bg-rose-100 text-rose-800 border-rose-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }
}
