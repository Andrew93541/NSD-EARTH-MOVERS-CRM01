<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'entry_date' => 'date',
        'total_amount' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    /**
     * In-memory registry of newly created journal entries in the current process
     * permitted to receive lines during their initial construction.
     *
     * @var array<int, float>
     */
    protected static array $unlockedEntryIds = [];

    public static function unlockForCreation(int $id): void
    {
        static::$unlockedEntryIds[$id] = microtime(true);
    }

    public static function lockAfterCreation(int $id): void
    {
        unset(static::$unlockedEntryIds[$id]);
    }

    public static function isUnlockedForCreation(int $id): bool
    {
        if (isset(static::$unlockedEntryIds[$id])) {
            // Unlocked within the current process for up to 60 seconds
            if (microtime(true) - static::$unlockedEntryIds[$id] < 60) {
                return true;
            }
            unset(static::$unlockedEntryIds[$id]);
        }

        return false;
    }

    public static function clearUnlockedRegistry(): void
    {
        static::$unlockedEntryIds = [];
    }

    protected static function booted(): void
    {
        static::created(function (JournalEntry $entry) {
            static::unlockForCreation($entry->id);
        });

        static::updating(function (JournalEntry $entry) {
            $originalStatus = $entry->getOriginal('status');

            if ($originalStatus === 'reversed') {
                throw new \DomainException("Reversed journal entry #{$entry->entry_number} is immutable and cannot be modified or reversed again.");
            }

            if ($originalStatus === 'posted') {
                $allowedFields = ['status', 'reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];
                $dirtyKeys = array_keys($entry->getDirty());
                $disallowedDirty = array_diff($dirtyKeys, $allowedFields);

                if (! empty($disallowedDirty)) {
                    throw new \DomainException("Posted journal entry #{$entry->entry_number} is immutable and cannot be modified. Field(s) [" . implode(', ', $disallowedDirty) . "] cannot be changed.");
                }

                if ($entry->isDirty('status') && $entry->status !== 'reversed') {
                    throw new \DomainException("Posted journal entry status can only transition to 'reversed'.");
                }
            }

            if ($originalStatus === 'draft' && $entry->isDirty('status') && $entry->status === 'posted') {
                if (! $entry->isBalanced()) {
                    throw new \DomainException("Cannot post unbalanced journal entry. Debits must equal credits.");
                }
            }
        });

        static::deleting(function (JournalEntry $entry) {
            if (in_array($entry->status, ['posted', 'reversed'])) {
                throw new \DomainException("Posted or reversed journal entry #{$entry->entry_number} cannot be deleted. Financial records are permanent and immutable.");
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function isBalanced(): bool
    {
        $debits = $this->lines->sum('debit');
        $credits = $this->lines->sum('credit');
        return bccomp((string) $debits, (string) $credits, 2) === 0;
    }
}
