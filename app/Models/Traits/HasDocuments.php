<?php

namespace App\Models\Traits;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    /**
     * All documents attached to this model.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Active documents ordered chronologically.
     */
    public function activeDocuments(): MorphMany
    {
        return $this->documents()->where('status', 'active')->orderBy('created_at', 'desc');
    }

    /**
     * Filter active documents by category.
     */
    public function documentsByCategory(string $category): MorphMany
    {
        return $this->activeDocuments()->where('category', $category);
    }

    /**
     * Check if a specific document category is attached.
     */
    public function hasDocumentCategory(string $category): bool
    {
        return $this->documentsByCategory($category)->exists();
    }
}
