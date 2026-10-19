<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations()
    {
        return $this->hasMany(InvoicePaymentAllocation::class, 'invoice_payment_id');
    }

    public function allocatedAmount(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function unallocatedBalance(): float
    {
        return max(0.0, (float) $this->amount - $this->allocatedAmount());
    }
}
