<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContract extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'completion_date' => 'date',
        'contract_value' => 'decimal:2',
        'retention_applicable' => 'boolean',
        'retention_rate_percent' => 'decimal:2',
        'max_retention_cap' => 'decimal:2',
        'custom_stages_config' => 'array',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(ClientCompletionCertificate::class);
    }

    public function retentionClaims(): HasMany
    {
        return $this->hasMany(RetentionReleaseClaim::class);
    }

    public function totalWithheldRetention(): float
    {
        $raw = (float) $this->invoices()
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->sum('retention_amount');

        if ($this->max_retention_cap && $raw > (float) $this->max_retention_cap) {
            return (float) $this->max_retention_cap;
        }

        return $raw;
    }

    public function totalClaimedRetention(): float
    {
        return (float) $this->retentionClaims()
            ->whereNotIn('status', ['draft', 'rejected'])
            ->sum('claimed_amount');
    }

    public function totalApprovedRetention(): float
    {
        return (float) $this->retentionClaims()
            ->whereIn('status', ['approved', 'client_submitted', 'partially_received', 'received', 'closed'])
            ->sum('approved_amount');
    }

    public function totalReceivedRetention(): float
    {
        return (float) $this->retentionClaims()
            ->sum('received_amount');
    }

    public function outstandingRetainedBalance(): float
    {
        return max(0.0, round($this->totalWithheldRetention() - $this->totalReceivedRetention(), 2));
    }

    public function getPracticalCompletionCertificate(): ?ClientCompletionCertificate
    {
        return $this->certificates()
            ->whereIn('certificate_type', ['practical_completion', 'handover_note'])
            ->where('status', 'verified')
            ->latest('issue_date')
            ->first();
    }

    public function getDlpCertificate(): ?ClientCompletionCertificate
    {
        return $this->certificates()
            ->whereIn('certificate_type', ['dlp_clearance', 'defect_free_certificate'])
            ->where('status', 'verified')
            ->latest('issue_date')
            ->first();
    }

    public function dlpEffectiveStartDate(): ?Carbon
    {
        $cert = $this->getPracticalCompletionCertificate();
        if ($cert) {
            return Carbon::parse($cert->effective_date ?? $cert->issue_date);
        }

        if ($this->completion_date) {
            return Carbon::parse($this->completion_date);
        }

        return null;
    }

    public function dlpExpiryDate(): ?Carbon
    {
        $start = $this->dlpEffectiveStartDate();
        if (! $start) {
            return null;
        }

        return $start->copy()->addMonths($this->dlp_duration_months ?? 12);
    }

    public function isDlpExpired(): bool
    {
        $expiry = $this->dlpExpiryDate();
        if (! $expiry) {
            return false;
        }

        return Carbon::now()->gte($expiry);
    }
}
