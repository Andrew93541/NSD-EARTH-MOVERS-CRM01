<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePaymentAllocation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function invoicePayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function clientInvoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }
}
