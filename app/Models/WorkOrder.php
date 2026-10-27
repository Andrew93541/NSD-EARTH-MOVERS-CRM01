<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'total_amount' => 'decimal:2',
        'extracted_data' => 'array',
        'is_duplicate_flagged' => 'boolean',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'duplicate_resolved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function assignedSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_supervisor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function duplicateMatchedOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_matched_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class, 'work_order_id')->orderBy('item_number');
    }

    public function isClientIssued(): bool
    {
        return $this->order_type === 'client_issued';
    }

    public function isInternal(): bool
    {
        return $this->order_type === 'internal';
    }

    public function isVerified(): bool
    {
        return $this->extraction_status === 'verified';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'in_progress', 'completed'], true);
    }

    public function primaryDocument(): ?Document
    {
        return $this->documents()
            ->where('status', 'active')
            ->where('mime_type', 'application/pdf')
            ->latest()
            ->first() ?? $this->documents()->where('status', 'active')->latest()->first();
    }

    /**
     * Generate a unique sequential order number per company and month.
     */
    public static function generateOrderNumber(int $companyId, string $type = 'client_issued'): string
    {
        $prefix = $type === 'internal' ? 'INT' : 'WO';
        $period = now()->format('Ym');
        $base = "{$prefix}-{$period}-";

        $lastOrder = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('order_number', 'LIKE', "{$base}%")
            ->orderBy('order_number', 'desc')
            ->first();

        $nextSeq = 1;
        if ($lastOrder && preg_match('/-(\d{4})$/', $lastOrder->order_number, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s%04d', $base, $nextSeq);
    }
}
