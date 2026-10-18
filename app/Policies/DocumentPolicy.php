<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\MeasurementBookEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RetentionReleaseClaim;
use App\Models\Site;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorWorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DocumentPolicy
{
    /**
     * Check if user is an active member of the company.
     */
    protected function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Determine whether the user can view or preview the document.
     */
    public function view(User $user, Document $document): bool
    {
        if (! $this->belongsToCompany($user, $document->company_id)) {
            return false;
        }

        $roleSlug = $user->currentRole()?->slug;

        // Confidential documents (HR files, payroll batches, bank docs) are restricted
        if ($document->is_confidential) {
            if (! in_array($roleSlug, ['owner', 'admin', 'hr_officer', 'accountant'], true)) {
                return false;
            }
        }

        // Verify access to parent record
        $parent = $document->documentable;
        if (! $parent) {
            return true;
        }

        return $this->canAccessParent($user, $parent);
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Determine whether the user can upload a document to a parent record.
     */
    public function upload(User $user, Model $parent): bool
    {
        $companyId = $parent->company_id ?? session('current_company_id');
        if (! $companyId || ! $this->belongsToCompany($user, $companyId)) {
            return false;
        }

        return $this->canAccessParent($user, $parent);
    }

    /**
     * Determine whether the user can replace or version the document.
     */
    public function replace(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        if ($document->isParentImmutable()) {
            return false;
        }

        $roleSlug = $user->currentRole()?->slug;
        if (in_array($roleSlug, ['owner', 'admin'], true)) {
            return true;
        }

        return $document->uploaded_by === $user->id;
    }

    /**
     * Determine whether the user can delete or archive the document.
     */
    public function delete(User $user, Document $document): bool
    {
        if (! $this->belongsToCompany($user, $document->company_id)) {
            return false;
        }

        // Statutory & audit protection
        if ($document->isParentImmutable()) {
            return false;
        }

        $roleSlug = $user->currentRole()?->slug;
        if (in_array($roleSlug, ['owner', 'admin'], true)) {
            return true;
        }

        // Uploader can delete their own document if they hold parent access
        if ($document->uploaded_by === $user->id && $this->view($user, $document)) {
            return true;
        }

        return false;
    }

    /**
     * Evaluate parent record domain permissions.
     */
    protected function canAccessParent(User $user, Model $parent): bool
    {
        $roleSlug = $user->currentRole()?->slug;
        if (in_array($roleSlug, ['owner', 'admin'], true) || $user->hasPermission('*')) {
            return true;
        }

        if ($parent instanceof Project) {
            return $user->hasPermission('projects.view') || $user->hasPermission('projects.*') || in_array($roleSlug, ['project_manager', 'site_supervisor', 'accountant'], true);
        }

        if ($parent instanceof Site) {
            return $user->hasPermission('sites.view') || $user->hasPermission('sites.*') || in_array($roleSlug, ['project_manager', 'site_supervisor'], true);
        }

        if ($parent instanceof PurchaseRequest) {
            return $user->hasPermission('procurement.*') || ($parent->requested_by ?? null) === $user->id || in_array($roleSlug, ['project_manager', 'site_supervisor', 'procurement_officer'], true);
        }

        if ($parent instanceof PurchaseOrder || $parent instanceof GoodsReceipt) {
            return $user->hasPermission('procurement.*') || in_array($roleSlug, ['procurement_officer', 'storekeeper', 'project_manager', 'accountant'], true);
        }

        if ($parent instanceof Employee) {
            return in_array($roleSlug, ['owner', 'admin', 'hr_officer'], true);
        }

        if ($parent instanceof PayrollPeriod || $parent instanceof PayrollRecord) {
            return in_array($roleSlug, ['owner', 'admin', 'hr_officer', 'accountant'], true);
        }

        if ($parent instanceof SubcontractorWorkOrder || $parent instanceof MeasurementBookEntry || $parent instanceof SubcontractorBill) {
            return in_array($roleSlug, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant', 'procurement_officer'], true);
        }

        if ($parent instanceof FixedAsset || $parent instanceof Loan || $parent instanceof RetentionReleaseClaim || $parent instanceof JournalEntry) {
            return in_array($roleSlug, ['owner', 'admin', 'accountant'], true) || $user->hasPermission('finance.*') || $user->hasPermission('accounting.*');
        }

        return true;
    }
}
