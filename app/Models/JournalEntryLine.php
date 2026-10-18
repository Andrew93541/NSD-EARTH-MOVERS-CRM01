<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalEntryLine $line) {
            $entryId = $line->journal_entry_id;
            if (! $entryId) {
                return;
            }

            $entry = $line->relationLoaded('entry')
                ? $line->entry
                : JournalEntry::withoutGlobalScopes()->find($entryId);

            if ($entry && in_array($entry->status, ['posted', 'reversed'])) {
                if (! JournalEntry::isUnlockedForCreation($entry->id)) {
                    throw new \DomainException("Posted journal entry #{$entry->entry_number} is immutable. New lines cannot be added to a posted or reversed entry.");
                }
            }
        });

        static::updating(function (JournalEntryLine $line) {
            $entry = $line->relationLoaded('entry')
                ? $line->entry
                : JournalEntry::withoutGlobalScopes()->find($line->journal_entry_id);

            if ($entry && in_array($entry->status, ['posted', 'reversed'])) {
                throw new \DomainException("Lines of posted journal entry #{$entry->entry_number} are immutable and cannot be modified.");
            }
        });

        static::deleting(function (JournalEntryLine $line) {
            $entry = $line->relationLoaded('entry')
                ? $line->entry
                : JournalEntry::withoutGlobalScopes()->find($line->journal_entry_id);

            if ($entry && in_array($entry->status, ['posted', 'reversed'])) {
                throw new \DomainException("Lines of posted journal entry #{$entry->entry_number} cannot be deleted. Financial records are permanent and immutable.");
            }
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
