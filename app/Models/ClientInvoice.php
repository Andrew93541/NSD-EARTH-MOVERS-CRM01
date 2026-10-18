<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'retention_rate_percent' => 'decimal:2',
        'retention_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clientContract(): BelongsTo
    {
        return $this->belongsTo(ClientContract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function retentionClaimItems(): HasMany
    {
        return $this->hasMany(RetentionClaimInvoiceItem::class);
    }

    public function balanceDue(): float
    {
        return (float) ($this->total_amount - $this->paid_amount);
    }

    public function claimedRetention(): float
    {
        return (float) $this->retentionClaimItems()
            ->whereHas('claim', function ($q) {
                $q->whereNotIn('status', ['draft', 'rejected']);
            })
            ->sum('claim_allocated_amount');
    }

    public function unclaimedRetention(): float
    {
        return max(0.0, round((float) $this->retention_amount - $this->claimedRetention(), 2));
    }
}
