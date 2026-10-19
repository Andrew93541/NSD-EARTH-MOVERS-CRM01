<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasDocuments;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Quotation extends Model
{
    use HasFactory, BelongsToCompany, HasDocuments;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'valid_until' => 'date',
        'converted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('item_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_project_id');
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * Compute and sync all totals, discounts, and taxes based on items and configuration.
     */
    public function recalculateTotals(): void
    {
        $subtotal = 0.00;
        foreach ($this->items as $item) {
            $subtotal += (float) $item->amount;
        }

        $discountAmount = 0.00;
        if ($this->discount_type === 'percentage') {
            $discountAmount = round(($subtotal * (float) $this->discount_rate) / 100, 2);
        } elseif ($this->discount_type === 'fixed') {
            $discountAmount = min($subtotal, (float) $this->discount_amount);
        }

        $taxableAmount = max(0.00, $subtotal - $discountAmount);

        $taxAmount = 0.00;
        if ($this->tax_type !== 'exempt' && (float) $this->tax_rate > 0) {
            $taxAmount = round(($taxableAmount * (float) $this->tax_rate) / 100, 2);
        }

        $totalAmount = round($taxableAmount + $taxAmount, 2);

        $this->updateQuietly([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Check if status transition is allowed.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        if ($this->status === $targetStatus) {
            return true;
        }

        // Converted quotations are immutable commercial records
        if ($this->converted_project_id !== null) {
            return false;
        }

        return match ($this->status) {
            'draft' => in_array($targetStatus, ['sent', 'rejected'], true),
            'sent' => in_array($targetStatus, ['accepted', 'rejected', 'expired', 'draft'], true),
            'accepted' => in_array($targetStatus, ['draft'], true), // Can revert to draft only if not converted
            'rejected', 'expired' => in_array($targetStatus, ['draft'], true),
            default => false,
        };
    }

    /**
     * Convert an accepted quotation into an active project.
     * Prevents duplicate project creation.
     */
    public function convertToProject(User $user, array $overrides = []): Project
    {
        if ($this->status !== 'accepted') {
            throw new DomainException("Only accepted quotations can be converted into a project. Current status: {$this->status}.");
        }

        if ($this->converted_project_id !== null) {
            throw new DomainException("Quotation {$this->quotation_number} has already been converted to project #{$this->converted_project_id}. Duplicate project creation is prevented.");
        }

        return DB::transaction(function () use ($user, $overrides) {
            // Lock quotation row to prevent race condition duplicate conversions
            $quotation = static::where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($quotation->converted_project_id !== null) {
                throw new DomainException("Quotation {$this->quotation_number} has already been converted to project #{$quotation->converted_project_id}. Duplicate project creation is prevented.");
            }

            $code = $overrides['code'] ?? ('PRJ-' . date('Y') . '-' . strtoupper(Str::random(4)));
            while (Project::where('code', $code)->exists()) {
                $code = 'PRJ-' . date('Y') . '-' . strtoupper(Str::random(4));
            }

            $project = Project::create([
                'company_id' => $quotation->company_id,
                'client_id' => $quotation->client_id,
                'name' => $overrides['name'] ?? $quotation->project_title,
                'code' => $code,
                'project_type' => $overrides['project_type'] ?? 'earthmoving',
                'contract_value' => $quotation->total_amount,
                'approved_budget' => $quotation->subtotal,
                'actual_cost' => 0.00,
                'status' => 'active',
                'start_date' => $overrides['start_date'] ?? now()->toDateString(),
                'target_completion_date' => $overrides['target_completion_date'] ?? now()->addMonths(6)->toDateString(),
                'progress_percent' => 0,
                'manager_id' => $overrides['manager_id'] ?? $user->id,
                'description' => $quotation->project_description,
            ]);

            // Link formal Client Contract for progress billing and retention tracking
            $contractNumber = 'CNT-' . date('Y') . '-' . strtoupper(Str::random(4));
            while (\App\Models\ClientContract::where('contract_number', $contractNumber)->exists()) {
                $contractNumber = 'CNT-' . date('Y') . '-' . strtoupper(Str::random(4));
            }

            \App\Models\ClientContract::create([
                'company_id' => $quotation->company_id,
                'client_id' => $quotation->client_id,
                'project_id' => $project->id,
                'contract_number' => $contractNumber,
                'title' => $project->name . ' - Contract Agreement',
                'contract_value' => $quotation->total_amount,
                'start_date' => $project->start_date,
                'completion_date' => $project->target_completion_date,
                'retention_applicable' => true,
                'retention_rate_percent' => 5.00,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            $quotation->update([
                'converted_project_id' => $project->id,
                'converted_at' => now(),
                'converted_by' => $user->id,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $quotation->company_id,
                'action' => 'convert_quotation_to_project',
                'auditable_type' => Quotation::class,
                'auditable_id' => $quotation->id,
                'old_values' => ['status' => 'accepted', 'converted_project_id' => null],
                'new_values' => ['converted_project_id' => $project->id, 'project_code' => $project->code],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $project;
        });
    }
}
