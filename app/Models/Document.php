<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'file_size' => 'integer',
        'is_confidential' => 'boolean',
        'version' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Polymorphic relation to parent business record.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User who uploaded the file.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * In case this document is replaced by a newer version.
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'superseded_by_id');
    }

    /**
     * Previous versions replaced by this document.
     */
    public function previousVersions(): HasMany
    {
        return $this->hasMany(Document::class, 'superseded_by_id');
    }

    /**
     * Scope to only active documents.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope documents visible to the given user based on confidentiality & parent permissions.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $role = $user->currentRole()?->slug;
        $isPrivileged = in_array($role, ['owner', 'admin', 'hr_officer', 'accountant'], true);

        if (! $isPrivileged) {
            $query->where('is_confidential', false);
        }

        $accessibleTypes = app(\App\Policies\DocumentPolicy::class)->getAccessibleParentTypes($user);

        if (empty($accessibleTypes)) {
            $query->whereRaw('1 = 0');
            return $query;
        }

        // Deny by default: only include documents with recognized, authorized parent types
        // Excludes unknown parent types, null documentable_type/id (orphaned), and parent types the user cannot access
        $query->whereNotNull('documentable_type')
            ->whereNotNull('documentable_id')
            ->whereIn('documentable_type', $accessibleTypes);

        return $query;
    }

    /**
     * Format byte size for human readability.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Whether this document can be previewed inline securely.
     */
    public function isPreviewable(): bool
    {
        return $this->isPdf() || $this->isImage();
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Determine if the document has passed anti-malware verification and is safe for user access.
     * Blocks infected, error, quarantined, and pending files unconditionally.
     * Blocks unscanned files in production and fail-closed environments.
     */
    public function isCleanForAccess(): bool
    {
        if ($this->scan_status === 'clean') {
            return true;
        }

        // Unconditionally block infected, error, quarantined, or pending files
        if (in_array($this->scan_status, ['infected', 'error', 'quarantined', 'pending'], true)) {
            return false;
        }

        // For 'unscanned' status: strictly permit only if in non-production AND allow_unscanned_dev is explicitly true AND fail_closed is false
        if ($this->scan_status === 'unscanned') {
            $isProduction = app()->environment('production');
            $isFailClosed = (bool) config('filesystems.scanner.fail_closed', $isProduction);
            $isDevAllowed = ! $isProduction && (bool) config('filesystems.scanner.allow_unscanned_dev', false);

            return $isDevAllowed && ! $isFailClosed;
        }

        return false;
    }

    /**
     * Category title label formatted for UI badges.
     */
    public function getCategoryLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->category));
    }

    /**
     * Detect whether the parent record is an immutable financial or legal record.
     * Posted journals, certified subcontractor bills, and settled loans prohibit permanent file deletion.
     */
    public function isParentImmutable(): bool
    {
        $parent = $this->documentable;
        if (! $parent) {
            return false;
        }

        if ($parent instanceof JournalEntry && in_array($parent->status, ['posted', 'reversed'], true)) {
            return true;
        }

        if ($parent instanceof SubcontractorBill && in_array($parent->status, ['certified', 'paid', 'partially_paid'], true)) {
            return true;
        }

        if ($parent instanceof Loan && $parent->status === 'settled') {
            return true;
        }

        if ($parent instanceof RetentionReleaseClaim && in_array($parent->status, ['approved', 'settled'], true)) {
            return true;
        }

        return false;
    }
}
