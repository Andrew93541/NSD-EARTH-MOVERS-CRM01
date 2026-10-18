<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\ClientContract;
use App\Models\ClientInvoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\RetentionClaimInvoiceItem;
use App\Models\RetentionClaimPayment;
use App\Models\RetentionReleaseClaim;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetentionService
{
    /**
     * Get detailed retention analysis and stage eligibility for a specific contract.
     */
    public static function getContractRetentionSummary(ClientContract $contract): array
    {
        $contract->loadMissing([
            'client',
            'project',
            'invoices' => function ($q) {
                $q->whereIn('status', ['approved', 'partially_paid', 'paid']);
            },
            'certificates',
            'retentionClaims.invoiceItems',
            'retentionClaims.payments',
        ]);

        $rawWithheld = (float) $contract->invoices->sum('retention_amount');
        $cap = $contract->max_retention_cap ? (float) $contract->max_retention_cap : null;
        $totalWithheld = $cap ? min($rawWithheld, $cap) : $rawWithheld;

        $claims = $contract->retentionClaims;
        $activeClaims = $claims->whereNotIn('status', ['draft', 'rejected']);

        $totalClaimed = (float) $activeClaims->sum('claimed_amount');
        $totalApproved = (float) $activeClaims->whereIn('status', ['approved', 'client_submitted', 'partially_received', 'received', 'closed'])->sum(function ($c) {
            return $c->approved_amount ?? $c->claimed_amount;
        });
        $totalReceived = (float) $claims->sum('received_amount');
        $outstandingWithheld = max(0.0, round($totalWithheld - $totalReceived, 2));

        $outstandingWithClient = (float) $activeClaims
            ->whereIn('status', ['client_submitted', 'partially_received'])
            ->sum(function ($c) {
                return $c->balanceDue();
            });

        // Certificates check
        $pcc = $contract->getPracticalCompletionCertificate();
        $dlpCert = $contract->getDlpCertificate();
        $dlpStart = $contract->dlpEffectiveStartDate();
        $dlpExpiry = $contract->dlpExpiryDate();
        $isDlpExpired = $contract->isDlpExpired();

        // Release stages evaluation
        $stages = [];

        if ($contract->release_terms_type === 'single_full_release') {
            $stageTarget = $totalWithheld;
            $previouslyClaimed = (float) $activeClaims->where('stage_key', 'full_release')->sum('claimed_amount');
            $isEligible = ($isDlpExpired && $dlpCert && $dlpCert->all_defects_rectified);

            $ineligibleReason = null;
            if (! $pcc && ! $contract->completion_date) {
                $ineligibleReason = 'Practical Completion Certificate required.';
            } elseif (! $isDlpExpired && $dlpExpiry) {
                $ineligibleReason = 'Defect Liability Period active until ' . $dlpExpiry->format('d M Y') . '.';
            } elseif (! $dlpCert) {
                $ineligibleReason = 'Defect Liability Period (DLP) Clearance Certificate required.';
            } elseif (! $dlpCert->all_defects_rectified) {
                $unresolved = $dlpCert->defect_count_reported - $dlpCert->defect_count_resolved;
                $ineligibleReason = "Defect rectification pending ({$unresolved} unresolved defects).";
            }

            $remainingEligible = $isEligible ? max(0.0, round($stageTarget - $previouslyClaimed, 2)) : 0.0;

            $stages[] = [
                'stage_key' => 'full_release',
                'stage_name' => '100% Full Retention Release (Post-DLP)',
                'percentage' => 100.0,
                'target_amount' => $stageTarget,
                'is_eligible' => $isEligible,
                'ineligible_reason' => $ineligibleReason,
                'previously_claimed' => $previouslyClaimed,
                'remaining_eligible' => $remainingEligible,
                'required_cert_type' => 'dlp_clearance',
                'cert_available' => (bool) $dlpCert,
            ];
        } else {
            // Default: staged_50_50 (50% Completion, 50% DLP Expiry)
            $stage1Target = round($totalWithheld * 0.50, 2);
            $stage2Target = max(0.0, round($totalWithheld - $stage1Target, 2));

            // Stage 1: Practical Completion
            $stage1Claimed = (float) $activeClaims->where('stage_key', 'stage_1_completion')->sum('claimed_amount');
            $stage1Eligible = ($pcc !== null && $pcc->status === 'verified');
            $stage1Reason = $stage1Eligible ? null : 'Practical Completion Certificate (PCC) required.';
            $stage1Remaining = $stage1Eligible ? max(0.0, round($stage1Target - $stage1Claimed, 2)) : 0.0;

            $stages[] = [
                'stage_key' => 'stage_1_completion',
                'stage_name' => 'Stage 1: Practical Completion Release (50%)',
                'percentage' => 50.0,
                'target_amount' => $stage1Target,
                'is_eligible' => $stage1Eligible,
                'ineligible_reason' => $stage1Reason,
                'previously_claimed' => $stage1Claimed,
                'remaining_eligible' => $stage1Remaining,
                'required_cert_type' => 'practical_completion',
                'cert_available' => (bool) $pcc,
                'cert' => $pcc,
            ];

            // Stage 2: DLP Expiry
            $stage2Claimed = (float) $activeClaims->where('stage_key', 'stage_2_dlp')->sum('claimed_amount');
            $stage2Eligible = ($stage1Eligible && $isDlpExpired && $dlpCert !== null && $dlpCert->all_defects_rectified);

            $stage2Reason = null;
            if (! $stage1Eligible) {
                $stage2Reason = 'Stage 1 completion prerequisite must be satisfied first.';
            } elseif (! $isDlpExpired && $dlpExpiry) {
                $diffMonths = (int) round(Carbon::now()->diffInMonths($dlpExpiry, false));
                $stage2Reason = 'Defect Liability Period active until ' . $dlpExpiry->format('d M Y') . ($diffMonths > 0 ? " ({$diffMonths} months remaining)" : '');
            } elseif (! $dlpCert) {
                $stage2Reason = 'Defect Liability Period (DLP) Clearance Certificate required.';
            } elseif (! $dlpCert->all_defects_rectified) {
                $unresolved = $dlpCert->defect_count_reported - $dlpCert->defect_count_resolved;
                $stage2Reason = "Outstanding defects pending rectification ({$unresolved} unresolved defects).";
            }

            $stage2Remaining = $stage2Eligible ? max(0.0, round($stage2Target - $stage2Claimed, 2)) : 0.0;

            $stages[] = [
                'stage_key' => 'stage_2_dlp',
                'stage_name' => 'Stage 2: Final Defect Liability Clearance (50%)',
                'percentage' => 50.0,
                'target_amount' => $stage2Target,
                'is_eligible' => $stage2Eligible,
                'ineligible_reason' => $stage2Reason,
                'previously_claimed' => $stage2Claimed,
                'remaining_eligible' => $stage2Remaining,
                'required_cert_type' => 'dlp_clearance',
                'cert_available' => (bool) $dlpCert,
                'cert' => $dlpCert,
            ];
        }

        $totalRemainingEligible = array_sum(array_column($stages, 'remaining_eligible'));

        return [
            'contract_id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'retention_rate_percent' => (float) $contract->retention_rate_percent,
            'max_retention_cap' => $cap,
            'raw_withheld' => $rawWithheld,
            'total_withheld' => $totalWithheld,
            'total_claimed' => $totalClaimed,
            'total_approved' => $totalApproved,
            'total_received' => $totalReceived,
            'outstanding_withheld' => $outstandingWithheld,
            'outstanding_with_client' => $outstandingWithClient,
            'total_remaining_eligible' => $totalRemainingEligible,
            'dlp_duration_months' => $contract->dlp_duration_months,
            'dlp_effective_start' => $dlpStart?->format('d M Y'),
            'dlp_expiry_date' => $dlpExpiry?->format('d M Y'),
            'is_dlp_expired' => $isDlpExpired,
            'has_pcc' => (bool) $pcc,
            'has_dlp_cert' => (bool) $dlpCert,
            'stages' => $stages,
        ];
    }

    /**
     * Get aggregate Retention Register KPI metrics across the company.
     */
    public static function getCompanyRetentionRegisterSummary(int $companyId): array
    {
        $contracts = ClientContract::where('company_id', $companyId)->get();

        $totalWithheld = 0.0;
        $totalEligible = 0.0;
        $totalReceived = 0.0;
        $totalOutstandingWithClient = 0.0;

        foreach ($contracts as $cnt) {
            $summary = self::getContractRetentionSummary($cnt);
            $totalWithheld += $summary['total_withheld'];
            $totalEligible += $summary['total_remaining_eligible'];
            $totalReceived += $summary['total_received'];
            $totalOutstandingWithClient += $summary['outstanding_with_client'];
        }

        // Claims status breakdown
        $claimsQuery = RetentionReleaseClaim::where('company_id', $companyId);
        $pendingReviewAmount = (float) (clone $claimsQuery)->where('status', 'submitted')->sum('claimed_amount');
        $approvedAwaitingClient = (float) (clone $claimsQuery)->where('status', 'approved')->sum('claimed_amount');
        
        $overdueClaims = (clone $claimsQuery)
            ->whereIn('status', ['client_submitted', 'partially_received'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->get();

        $overdueAmount = (float) $overdueClaims->sum(function ($c) {
            return $c->balanceDue();
        });

        $netOutstandingWithheld = max(0.0, round($totalWithheld - $totalReceived, 2));

        return [
            'total_withheld' => $totalWithheld,
            'total_eligible' => $totalEligible,
            'pending_review_amount' => $pendingReviewAmount,
            'approved_awaiting_client' => $approvedAwaitingClient,
            'outstanding_with_client' => $totalOutstandingWithClient,
            'total_received' => $totalReceived,
            'net_outstanding_withheld' => $netOutstandingWithheld,
            'overdue_amount' => $overdueAmount,
            'contracts_count' => $contracts->count(),
            'active_claims_count' => RetentionReleaseClaim::where('company_id', $companyId)->whereNotIn('status', ['closed', 'rejected'])->count(),
        ];
    }

    /**
     * Validate and create a retention release claim.
     */
    public static function createClaim(User $user, ClientContract $contract, array $data): RetentionReleaseClaim
    {
        return DB::transaction(function () use ($user, $contract, $data) {
            $lockedContract = ClientContract::where('id', $contract->id)->lockForUpdate()->firstOrFail();
            $summary = self::getContractRetentionSummary($lockedContract);

            $stageKey = $data['stage_key'];
            $stageConfig = collect($summary['stages'])->firstWhere('stage_key', $stageKey);

            if (! $stageConfig) {
                throw new \InvalidArgumentException("Invalid retention release stage: {$stageKey}");
            }

            if (! $stageConfig['is_eligible']) {
                throw new \InvalidArgumentException("Stage is not eligible for release: " . ($stageConfig['ineligible_reason'] ?? 'Prerequisites not met.'));
            }

            $requestedAmount = (float) $data['claimed_amount'];
            if ($requestedAmount <= 0) {
                throw new \InvalidArgumentException('Claim amount must be greater than zero.');
            }

            if ($requestedAmount > $stageConfig['remaining_eligible']) {
                throw new \InvalidArgumentException("Claim amount of ₹" . number_format($requestedAmount, 2) . " exceeds remaining eligible balance of ₹" . number_format($stageConfig['remaining_eligible'], 2));
            }

            // Find certificate if applicable
            $certId = $data['completion_certificate_id'] ?? null;
            if (! $certId && isset($stageConfig['cert'])) {
                $certId = $stageConfig['cert']->id;
            }

            $claimNumber = 'RET-CLM-' . date('Y') . '-' . strtoupper(Str::random(5));
            $isDirectSubmit = ! empty($data['submit_for_review']);

            $claim = RetentionReleaseClaim::create([
                'company_id' => $lockedContract->company_id,
                'client_contract_id' => $lockedContract->id,
                'client_id' => $lockedContract->client_id,
                'project_id' => $lockedContract->project_id,
                'completion_certificate_id' => $certId,
                'claim_number' => $claimNumber,
                'stage_key' => $stageKey,
                'stage_name' => $stageConfig['stage_name'],
                'eligible_amount' => $stageConfig['remaining_eligible'],
                'claimed_amount' => $requestedAmount,
                'approved_amount' => null,
                'received_amount' => 0.00,
                'status' => $isDirectSubmit ? 'submitted' : 'draft',
                'submission_date' => $isDirectSubmit ? Carbon::today() : null,
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : Carbon::today()->addDays(30),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // Allocate claim amount across source invoices
            $remainingToAllocate = $requestedAmount;
            $invoices = ClientInvoice::where('client_contract_id', $lockedContract->id)
                ->where('company_id', $lockedContract->company_id)
                ->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->lockForUpdate()
                ->get();

            // If invoices weren't explicitly linked to contract, link them by project_id
            if ($invoices->isEmpty()) {
                $invoices = ClientInvoice::where('project_id', $lockedContract->project_id)
                    ->where('client_id', $lockedContract->client_id)
                    ->where('company_id', $lockedContract->company_id)
                    ->whereIn('status', ['approved', 'partially_paid', 'paid'])
                    ->lockForUpdate()
                    ->get();
            }

            foreach ($invoices as $inv) {
                if ($remainingToAllocate <= 0) {
                    break;
                }

                $unclaimed = $inv->unclaimedRetention();
                if ($unclaimed <= 0) {
                    continue;
                }

                $alloc = min($remainingToAllocate, $unclaimed);
                RetentionClaimInvoiceItem::create([
                    'retention_release_claim_id' => $claim->id,
                    'client_invoice_id' => $inv->id,
                    'source_retention_amount' => (float) $inv->retention_amount,
                    'previously_claimed_amount' => $inv->claimedRetention(),
                    'claim_allocated_amount' => $alloc,
                ]);

                $remainingToAllocate -= $alloc;
            }

            AuditLog::create([
                'company_id' => $lockedContract->company_id,
                'user_id' => $user->id,
                'action' => 'create',
                'auditable_type' => RetentionReleaseClaim::class,
                'auditable_id' => $claim->id,
                'new_values' => [
                    'claim_number' => $claimNumber,
                    'claimed_amount' => $requestedAmount,
                    'status' => $claim->status,
                ],
            ]);

            return $claim;
        });
    }

    /**
     * Approve an internal retention release claim.
     */
    public static function approveClaim(User $user, RetentionReleaseClaim $claim, ?string $comments = null, ?float $approvedAmount = null): RetentionReleaseClaim
    {
        return DB::transaction(function () use ($user, $claim, $comments, $approvedAmount) {
            $lockedClaim = RetentionReleaseClaim::where('id', $claim->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedClaim->status, ['submitted', 'draft'])) {
                throw new \InvalidArgumentException("Claim cannot be approved in its current status: {$lockedClaim->status}");
            }

            // Segregation of duties: Creator cannot approve their own claim unless Admin/Owner
            $roleSlug = $user->currentRole()?->slug;
            if ($lockedClaim->created_by === $user->id && ! in_array($roleSlug, ['owner', 'admin'])) {
                throw new \InvalidArgumentException('Segregation of duties: You cannot approve a retention claim you prepared.');
            }

            $amount = $approvedAmount !== null ? (float) $approvedAmount : (float) $lockedClaim->claimed_amount;
            if ($amount <= 0 || $amount > (float) $lockedClaim->claimed_amount) {
                throw new \InvalidArgumentException('Approved amount must be positive and cannot exceed claimed amount.');
            }

            $lockedClaim->update([
                'status' => 'approved',
                'approved_amount' => $amount,
                'reviewed_by' => $user->id,
                'reviewed_at' => Carbon::now(),
                'approval_remarks' => $comments,
            ]);

            AuditLog::create([
                'company_id' => $lockedClaim->company_id,
                'user_id' => $user->id,
                'action' => 'approve',
                'auditable_type' => RetentionReleaseClaim::class,
                'auditable_id' => $lockedClaim->id,
                'new_values' => [
                    'status' => 'approved',
                    'approved_amount' => $amount,
                    'comments' => $comments,
                ],
            ]);

            return $lockedClaim;
        });
    }

    /**
     * Reject an internal retention release claim.
     */
    public static function rejectClaim(User $user, RetentionReleaseClaim $claim, string $reason): RetentionReleaseClaim
    {
        return DB::transaction(function () use ($user, $claim, $reason) {
            $lockedClaim = RetentionReleaseClaim::where('id', $claim->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedClaim->status, ['submitted', 'draft'])) {
                throw new \InvalidArgumentException("Claim cannot be rejected in its current status: {$lockedClaim->status}");
            }

            if (empty(trim($reason))) {
                throw new \InvalidArgumentException('A mandatory rejection reason is required.');
            }

            $lockedClaim->update([
                'status' => 'rejected',
                'reviewed_by' => $user->id,
                'reviewed_at' => Carbon::now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::create([
                'company_id' => $lockedClaim->company_id,
                'user_id' => $user->id,
                'action' => 'reject',
                'auditable_type' => RetentionReleaseClaim::class,
                'auditable_id' => $lockedClaim->id,
                'new_values' => [
                    'status' => 'rejected',
                    'reason' => $reason,
                ],
            ]);

            return $lockedClaim;
        });
    }

    /**
     * Mark an approved claim as submitted/dispatched to the client.
     */
    public static function markClientSubmitted(User $user, RetentionReleaseClaim $claim, string $clientReference, ?string $submissionDate = null): RetentionReleaseClaim
    {
        return DB::transaction(function () use ($user, $claim, $clientReference, $submissionDate) {
            $lockedClaim = RetentionReleaseClaim::where('id', $claim->id)->lockForUpdate()->firstOrFail();

            if ($lockedClaim->status !== 'approved') {
                throw new \InvalidArgumentException("Claim must be in approved status before submitting to client.");
            }

            if (empty(trim($clientReference))) {
                throw new \InvalidArgumentException('Client claim reference number is required.');
            }

            $lockedClaim->update([
                'status' => 'client_submitted',
                'client_reference' => $clientReference,
                'client_submitted_at' => $submissionDate ? Carbon::parse($submissionDate) : Carbon::now(),
            ]);

            // Create balanced double-entry accounting journal if not already posted
            self::postRetentionClaimJournal($lockedClaim, $user);

            AuditLog::create([
                'company_id' => $lockedClaim->company_id,
                'user_id' => $user->id,
                'action' => 'update',
                'auditable_type' => RetentionReleaseClaim::class,
                'auditable_id' => $lockedClaim->id,
                'new_values' => [
                    'status' => 'client_submitted',
                    'client_reference' => $clientReference,
                ],
            ]);

            return $lockedClaim;
        });
    }

    /**
     * Record a partial or full payment receipt against a client retention claim.
     */
    public static function recordClaimReceipt(User $user, RetentionReleaseClaim $claim, array $data): RetentionClaimPayment
    {
        return DB::transaction(function () use ($user, $claim, $data) {
            $lockedClaim = RetentionReleaseClaim::where('id', $claim->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedClaim->status, ['approved', 'client_submitted', 'partially_received'])) {
                throw new \InvalidArgumentException("Payments cannot be recorded on a claim with status: {$lockedClaim->status}");
            }

            $currentDue = $lockedClaim->balanceDue();
            $receiptAmount = (float) $data['amount'];

            if ($receiptAmount <= 0) {
                throw new \InvalidArgumentException('Receipt amount must be greater than zero.');
            }

            if ($receiptAmount > $currentDue) {
                throw new \InvalidArgumentException("Receipt amount of ₹" . number_format($receiptAmount, 2) . " exceeds current outstanding claim balance of ₹" . number_format($currentDue, 2));
            }

            $receiptNumber = 'RET-RCP-' . date('Y') . '-' . strtoupper(Str::random(5));

            $payment = RetentionClaimPayment::create([
                'company_id' => $lockedClaim->company_id,
                'retention_release_claim_id' => $lockedClaim->id,
                'receipt_number' => $receiptNumber,
                'payment_date' => Carbon::parse($data['payment_date'] ?? Carbon::today()),
                'amount' => $receiptAmount,
                'payment_mode' => $data['payment_mode'] ?? 'bank_transfer',
                'reference_number' => $data['reference_number'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'recorded_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $newReceived = (float) $lockedClaim->received_amount + $receiptAmount;
            $basis = (float) ($lockedClaim->approved_amount ?? $lockedClaim->claimed_amount);
            $newStatus = ($newReceived >= $basis) ? 'received' : 'partially_received';

            $lockedClaim->update([
                'received_amount' => $newReceived,
                'status' => $newStatus,
            ]);

            // Post double-entry receipt journal (Bank Debit, Accounts Receivable Credit)
            self::postRetentionReceiptJournal($payment, $lockedClaim, $user);

            AuditLog::create([
                'company_id' => $lockedClaim->company_id,
                'user_id' => $user->id,
                'action' => 'create',
                'auditable_type' => RetentionClaimPayment::class,
                'auditable_id' => $payment->id,
                'new_values' => [
                    'receipt_number' => $receiptNumber,
                    'amount' => $receiptAmount,
                    'claim_status' => $newStatus,
                ],
            ]);

            return $payment;
        });
    }

    /**
     * Post balanced journal entry upon claim submission to client.
     */
    protected static function postRetentionClaimJournal(RetentionReleaseClaim $claim, User $user): void
    {
        $existing = JournalEntry::where('company_id', $claim->company_id)
            ->where('reference_type', 'retention_release_claims')
            ->where('reference_id', $claim->id)
            ->exists();

        if ($existing) {
            return;
        }

        $amount = (float) ($claim->approved_amount ?? $claim->claimed_amount);
        if ($amount <= 0) {
            return;
        }

        $arAccount = Account::where('company_id', $claim->company_id)->where('code', '1020')->first();
        $retentionAssetAccount = Account::where('company_id', $claim->company_id)->where('code', '1025')->first();

        // If specific retention asset account doesn't exist, create it or use 1020
        if (! $retentionAssetAccount) {
            $retentionAssetAccount = Account::firstOrCreate([
                'company_id' => $claim->company_id,
                'code' => '1025',
            ], [
                'name' => 'Retention Money Withheld by Clients',
                'account_type' => 'asset',
                'sub_type' => 'current_asset',
                'is_system' => true,
            ]);
        }

        if (! $arAccount) {
            return;
        }

        $entryNumber = 'JV-RET-' . date('Y') . '-' . strtoupper(Str::random(5));
        $je = JournalEntry::create([
            'company_id' => $claim->company_id,
            'entry_number' => $entryNumber,
            'entry_date' => Carbon::today(),
            'reference_type' => 'retention_release_claims',
            'reference_id' => $claim->id,
            'narration' => "Retention release claim billed to client: {$claim->claim_number} ({$claim->stage_name})",
            'total_amount' => $amount,
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        // Debit: Accounts Receivable (Net due from client)
        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $arAccount->id,
            'project_id' => $claim->project_id,
            'debit' => $amount,
            'credit' => 0.00,
            'description' => "Client retention receivable for {$claim->claim_number}",
        ]);

        // Credit: Retention Asset / Withheld balance (Transfers from withheld to active claim)
        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $retentionAssetAccount->id,
            'project_id' => $claim->project_id,
            'debit' => 0.00,
            'credit' => $amount,
            'description' => "Release of withheld retention for {$claim->claim_number}",
        ]);
    }

    /**
     * Post balanced journal entry upon receiving retention payment from client.
     */
    protected static function postRetentionReceiptJournal(RetentionClaimPayment $payment, RetentionReleaseClaim $claim, User $user): void
    {
        $existing = JournalEntry::where('company_id', $claim->company_id)
            ->where('reference_type', 'retention_claim_payments')
            ->where('reference_id', $payment->id)
            ->exists();

        if ($existing) {
            return;
        }

        $bankAccountModel = $payment->bankAccount;
        $cashAcc = null;
        if ($bankAccountModel && $bankAccountModel->account_id) {
            $cashAcc = Account::find($bankAccountModel->account_id);
        }

        if (! $cashAcc) {
            $cashAcc = Account::where('company_id', $claim->company_id)->where('code', '1010')->first();
        }

        $arAccount = Account::where('company_id', $claim->company_id)->where('code', '1020')->first();

        if (! $cashAcc || ! $arAccount) {
            return;
        }

        $amount = (float) $payment->amount;
        $entryNumber = 'JV-REC-' . date('Y') . '-' . strtoupper(Str::random(5));

        $je = JournalEntry::create([
            'company_id' => $claim->company_id,
            'entry_number' => $entryNumber,
            'entry_date' => $payment->payment_date,
            'reference_type' => 'retention_claim_payments',
            'reference_id' => $payment->id,
            'narration' => "Receipt for client retention claim {$claim->claim_number} - Receipt {$payment->receipt_number}",
            'total_amount' => $amount,
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        // Debit: Bank Account (Cash Inflow)
        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $cashAcc->id,
            'project_id' => $claim->project_id,
            'debit' => $amount,
            'credit' => 0.00,
            'description' => "Bank settlement for retention receipt {$payment->receipt_number}",
        ]);

        // Credit: Accounts Receivable (Settles the claim)
        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $arAccount->id,
            'project_id' => $claim->project_id,
            'debit' => 0.00,
            'credit' => $amount,
            'description' => "Settlement of retention receivable for {$claim->claim_number}",
        ]);
    }
}
