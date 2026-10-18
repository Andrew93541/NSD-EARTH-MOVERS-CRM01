<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'work_categories' => 'array',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function subcontractorWorkOrders(): HasMany
    {
        return $this->hasMany(SubcontractorWorkOrder::class, 'subcontractor_id');
    }

    public function measurementBookEntries(): HasMany
    {
        return $this->hasMany(MeasurementBookEntry::class, 'subcontractor_id');
    }

    public function subcontractorBills(): HasMany
    {
        return $this->hasMany(SubcontractorBill::class, 'subcontractor_id');
    }

    public function scopeSubcontractors($query)
    {
        return $query->whereIn('vendor_type', ['subcontractor', 'both']);
    }

    public function scopeSuppliers($query)
    {
        return $query->whereIn('vendor_type', ['supplier', 'both']);
    }

    public function isSubcontractor(): bool
    {
        return in_array($this->vendor_type, ['subcontractor', 'both']);
    }

    /**
     * Get default TDS rate percentage based on configured Section 194C classification.
     */
    public function defaultTdsRate(): float
    {
        return match ($this->tds_section) {
            '194C_individual_1%' => 1.00,
            '194C_company_2%' => 2.00,
            default => 2.00,
        };
    }
}
