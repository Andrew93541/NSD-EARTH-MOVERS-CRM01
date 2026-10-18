<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetentionReleaseClaim extends Model
{
    use HasFactory, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'eligible_amount' => 'decimal:2',
        'claimed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'submission_date' => 'date',
        'client_submitted_at' => 'datetime',
        'due_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ClientContract::class, 'client_contract_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(ClientCompletionCertificate::class, 'completion_certificate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(RetentionClaimInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RetentionClaimPayment::class);
    }

    public function balanceDue(): float
    {
        $basis = (float) ($this->approved_amount !== null ? $this->approved_amount : $this->claimed_amount);
        return max(0.0, round($basis - (float) $this->received_amount, 2));
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
            'submitted' => 'bg-amber-50 text-amber-800 border-amber-200',
            'approved' => 'bg-blue-50 text-blue-700 border-blue-200',
            'rejected' => 'bg-red-50 text-red-700 border-red-200',
            'client_submitted' => 'bg-purple-50 text-purple-700 border-purple-200',
            'partially_received' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            'received', 'closed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft Claim',
            'submitted' => 'Under Review',
            'approved' => 'Approved Internally',
            'rejected' => 'Claim Rejected',
            'client_submitted' => 'Claimed to Client',
            'partially_received' => 'Partially Received',
            'received' => 'Fully Received',
            'closed' => 'Settled & Closed',
            default => ucwords(str_replace('_', ' ', $this->status)),
        };
    }
}
