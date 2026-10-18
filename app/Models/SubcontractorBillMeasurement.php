<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractorBillMeasurement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'allocated_quantity' => 'decimal:3',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SubcontractorBill::class, 'subcontractor_bill_id');
    }

    public function measurementBookEntry(): BelongsTo
    {
        return $this->belongsTo(MeasurementBookEntry::class, 'measurement_book_entry_id');
    }
}
