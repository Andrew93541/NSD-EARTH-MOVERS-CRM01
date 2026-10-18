<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCompletionCertificate extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'effective_date' => 'date',
        'dlp_expiry_date' => 'date',
        'defect_count_reported' => 'integer',
        'defect_count_resolved' => 'integer',
        'all_defects_rectified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ClientContract::class, 'client_contract_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function typeLabel(): string
    {
        return match ($this->certificate_type) {
            'practical_completion' => 'Practical Completion Certificate (PCC)',
            'handover_note' => 'Project Handover & Taking-Over Certificate',
            'dlp_clearance' => 'Defect Liability Period (DLP) Clearance Certificate',
            'defect_free_certificate' => 'Final Defect-Free Certificate',
            'final_measurement_clearance' => 'Joint Final Measurement Clearance',
            default => ucwords(str_replace('_', ' ', $this->certificate_type)),
        };
    }
}
