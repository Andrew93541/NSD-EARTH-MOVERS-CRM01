<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AssetDepreciationEntry;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Loan;
use App\Models\LoanEmiSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentAccountingService
{
    /**
     * Ensure standard equipment and loan accounts exist for the company.
     */
    public static function getOrCreateAccount(int $companyId, string $code, string $name, string $type, ?string $subType = null): Account
    {
        return Account::firstOrCreate([
            'company_id' => $companyId,
            'code' => $code,
        ], [
            'name' => $name,
            'account_type' => $type,
            'sub_type' => $subType,
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Post an equipment loan EMI settlement and generate balanced double-entry journals.
     *
     * Accounting Pattern:
     * - Debit:  Loan Liability (2010)  -> Principal component
     * - Debit:  Interest Expense (5020) -> Interest component
     * - Credit: Bank Account (1010)    -> Total EMI payment
     */
    public static function postEmiPayment(LoanEmiSchedule $schedule, array $data, User $user): JournalEntry
    {
        return DB::transaction(function () use ($schedule, $data, $user) {
            $lockedSchedule = LoanEmiSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

            if ($lockedSchedule->status === 'paid' || $lockedSchedule->journal_entry_id !== null) {
                throw new \InvalidArgumentException("Installment #{$lockedSchedule->installment_number} has already been settled and posted. Duplicate posting prevented.");
            }

            $loan = Loan::where('id', $lockedSchedule->loan_id)->lockForUpdate()->firstOrFail();
            $companyId = $loan->company_id;

            $paidDate = ! empty($data['paid_date']) ? Carbon::parse($data['paid_date']) : Carbon::today();
            $paymentRef = $data['payment_ref'] ?? 'AUTODEBIT-NACH';

            // Resolve Bank Account
            $bankAccount = null;
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::where('id', $data['bank_account_id'])
                    ->where('company_id', $companyId)
                    ->first();
            }

            $bankLedgerAccount = null;
            if ($bankAccount && $bankAccount->account_id) {
                $bankLedgerAccount = Account::find($bankAccount->account_id);
            }
            if (! $bankLedgerAccount) {
                $bankLedgerAccount = self::getOrCreateAccount($companyId, '1010', 'HDFC Current Bank Account', 'asset', 'current_asset');
            }

            // Resolve Loan Liability Account
            $liabilityAccount = $loan->liabilityAccount;
            if (! $liabilityAccount) {
                $liabilityAccount = self::getOrCreateAccount(
                    $companyId,
                    '2010',
                    'Equipment Financing Loans & Borrowings',
                    'liability',
                    'non_current_liability'
                );
            }

            // Resolve Interest Expense Account
            $interestAccount = $loan->interestAccount;
            if (! $interestAccount) {
                $interestAccount = self::getOrCreateAccount(
                    $companyId,
                    '5020',
                    'Loan Interest & Financing Charges',
                    'expense',
                    'finance_cost'
                );
            }

            $principal = (float) $lockedSchedule->principal_component;
            $interest = (float) $lockedSchedule->interest_component;
            $totalPayment = (float) $lockedSchedule->total_emi;

            // Strict double-entry balance validation
            $totalDebits = round($principal + $interest, 2);
            if (bccomp((string) $totalDebits, (string) $totalPayment, 2) !== 0) {
                throw new \InvalidArgumentException("EMI breakdown does not balance: Principal ({$principal}) + Interest ({$interest}) != Total ({$totalPayment})");
            }

            // Generate Immutable Journal Entry
            $journalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_number' => 'JV-EMI-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => $paidDate,
                'reference_type' => 'loan_emi_schedules',
                'reference_id' => $lockedSchedule->id,
                'narration' => "EMI Installment #{$lockedSchedule->installment_number} settlement for {$loan->asset_description} (Loan #{$loan->loan_account_number}) - Ref: {$paymentRef}",
                'total_amount' => $totalPayment,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // Debit Loan Liability (Principal Repaid)
            if ($principal > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $liabilityAccount->id,
                    'project_id' => $loan->fixedAsset?->project_id,
                    'debit' => $principal,
                    'credit' => 0.00,
                    'description' => "Principal repayment for Installment #{$lockedSchedule->installment_number}",
                ]);
            }

            // Debit Interest Expense (Cost of Debt)
            if ($interest > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $interestAccount->id,
                    'project_id' => $loan->fixedAsset?->project_id,
                    'debit' => $interest,
                    'credit' => 0.00,
                    'description' => "Interest charge for Installment #{$lockedSchedule->installment_number} ({$loan->annual_interest_rate}% p.a.)",
                ]);
            }

            // Credit Bank Account (Cash Outflow)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $bankLedgerAccount->id,
                'project_id' => null,
                'debit' => 0.00,
                'credit' => $totalPayment,
                'description' => "Bank settlement for EMI Installment #{$lockedSchedule->installment_number} via {$paymentRef}",
            ]);

            // Update Schedule Record
            $lockedSchedule->update([
                'status' => 'paid',
                'paid_date' => $paidDate,
                'payment_ref' => $paymentRef,
                'bank_account_id' => $bankAccount?->id,
                'journal_entry_id' => $journalEntry->id,
            ]);

            // Update Loan Outstanding Principal
            $newOutstanding = max(0.0, round((float) $loan->outstanding_principal - $principal, 2));
            $isFullyRepaid = ($newOutstanding <= 0);

            $loan->update([
                'outstanding_principal' => $newOutstanding,
                'status' => $isFullyRepaid ? 'closed' : $loan->status,
            ]);

            // Audit Trail
            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'post_emi_payment',
                'auditable_type' => LoanEmiSchedule::class,
                'auditable_id' => $lockedSchedule->id,
                'new_values' => [
                    'installment_number' => $lockedSchedule->installment_number,
                    'principal_component' => $principal,
                    'interest_component' => $interest,
                    'total_emi' => $totalPayment,
                    'journal_entry_number' => $journalEntry->entry_number,
                    'remaining_outstanding' => $newOutstanding,
                ],
            ]);

            return $journalEntry;
        });
    }

    /**
     * Reverse a posted EMI settlement with counter-balancing journal entries.
     */
    public static function reverseEmiPayment(LoanEmiSchedule $schedule, string $reversalReason, User $user): JournalEntry
    {
        return DB::transaction(function () use ($schedule, $reversalReason, $user) {
            $lockedSchedule = LoanEmiSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

            if ($lockedSchedule->status !== 'paid' || ! $lockedSchedule->journal_entry_id) {
                throw new \InvalidArgumentException("Installment #{$lockedSchedule->installment_number} is not in paid status or has no posted journal.");
            }

            if (strlen(trim($reversalReason)) < 5) {
                throw new \InvalidArgumentException("A mandatory reversal reason of at least 5 characters is required for financial audit compliance.");
            }

            $loan = Loan::where('id', $lockedSchedule->loan_id)->lockForUpdate()->firstOrFail();
            $originalEntry = JournalEntry::where('id', $lockedSchedule->journal_entry_id)->firstOrFail();

            // Mark original entry as reversed
            $originalEntry->update([
                'status' => 'reversed',
                'reversed_by' => $user->id,
                'reversed_at' => Carbon::now(),
                'reversal_reason' => $reversalReason,
            ]);

            // Post Reversing Counter-Journal Entry
            $reversalEntry = JournalEntry::create([
                'company_id' => $loan->company_id,
                'entry_number' => 'JV-REV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => Carbon::today(),
                'reference_type' => 'loan_emi_schedules',
                'reference_id' => $lockedSchedule->id,
                'narration' => "REVERSAL of {$originalEntry->entry_number}: {$reversalReason}",
                'total_amount' => $originalEntry->total_amount,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            foreach ($originalEntry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'project_id' => $line->project_id,
                    'debit' => $line->credit, // Invert debit and credit
                    'credit' => $line->debit,
                    'description' => "Reversal: {$line->description}",
                ]);
            }

            // Restore Loan Principal
            $restoredPrincipal = round((float) $loan->outstanding_principal + (float) $lockedSchedule->principal_component, 2);
            $loan->update([
                'outstanding_principal' => $restoredPrincipal,
                'status' => 'active',
            ]);

            // Reset Schedule Record
            $lockedSchedule->update([
                'status' => 'pending',
                'paid_date' => null,
                'payment_ref' => null,
                'bank_account_id' => null,
                'journal_entry_id' => null,
            ]);

            // Audit
            AuditLog::create([
                'company_id' => $loan->company_id,
                'user_id' => $user->id,
                'action' => 'reverse_emi_payment',
                'auditable_type' => LoanEmiSchedule::class,
                'auditable_id' => $lockedSchedule->id,
                'new_values' => [
                    'original_journal' => $originalEntry->entry_number,
                    'reversal_journal' => $reversalEntry->entry_number,
                    'reason' => $reversalReason,
                    'restored_outstanding' => $restoredPrincipal,
                ],
            ]);

            return $reversalEntry;
        });
    }

    /**
     * Calculate periodic depreciation for a fixed asset.
     * Supports Straight-Line (SLM) and Written-Down Value (WDV) methods with partial-period pro-rata logic.
     */
    public static function calculateDepreciation(FixedAsset $asset, Carbon $periodStart, Carbon $periodEnd, User $user): AssetDepreciationEntry
    {
        if (in_array($asset->status, ['disposed', 'retired'])) {
            throw new \InvalidArgumentException("Asset {$asset->asset_code} is {$asset->status} and cannot be depreciated.");
        }

        // Prevent duplicate calculation for the exact period
        $existing = AssetDepreciationEntry::where('fixed_asset_id', $asset->id)
            ->whereDate('period_start_date', $periodStart->toDateString())
            ->whereDate('period_end_date', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        $openingBookValue = (float) $asset->current_book_value;
        $residualValue = (float) $asset->residual_value;
        $maxDepreciable = max(0.0, round($openingBookValue - $residualValue, 2));

        if ($maxDepreciable <= 0.0) {
            throw new \InvalidArgumentException("Asset {$asset->asset_code} has reached its residual salvage value (₹" . number_format($residualValue, 2) . ") and cannot be depreciated further.");
        }

        $method = $asset->depreciation_method;
        $daysInMonth = $periodStart->daysInMonth;

        // Check if partial period (e.g. capitalized during this period)
        $assetStartDate = Carbon::parse($asset->depreciation_start_date ?? $asset->capitalization_date);
        $activeStartDate = $assetStartDate->greaterThan($periodStart) ? $assetStartDate : $periodStart;
        $activeDays = max(1, min($daysInMonth, $activeStartDate->diffInDays($periodEnd) + 1));
        $isPartial = ($activeDays < $daysInMonth);

        $depreciationAmount = 0.0;

        if ($method === 'written_down_value') {
            $annualRate = (float) ($asset->depreciation_rate_percent ?? 15.0);
            $monthlyRate = ($annualRate / 100) / 12;
            $fullMonthlyDepr = round($openingBookValue * $monthlyRate, 2);
            $depreciationAmount = $isPartial ? round($fullMonthlyDepr * ($activeDays / $daysInMonth), 2) : $fullMonthlyDepr;
        } else {
            // Straight-Line Method (SLM)
            $totalDepreciableBasis = max(0.0, (float) $asset->total_capitalized_cost - $residualValue);
            $usefulMonths = max(1, (int) $asset->useful_life_months);
            $fullMonthlyDepr = round($totalDepreciableBasis / $usefulMonths, 2);
            $depreciationAmount = $isPartial ? round($fullMonthlyDepr * ($activeDays / $daysInMonth), 2) : $fullMonthlyDepr;
        }

        // Cap so closing book value never drops below residual value
        $depreciationAmount = min($depreciationAmount, $maxDepreciable);

        $closingBookValue = round($openingBookValue - $depreciationAmount, 2);
        $accumulatedAfter = round((float) $asset->accumulated_depreciation + $depreciationAmount, 2);

        // Derive Financial Year (e.g., April to March Indian FY)
        $fyYear = $periodEnd->month >= 4 ? $periodEnd->year : $periodEnd->year - 1;
        $financialYear = $fyYear . '-' . ($fyYear + 1);

        return AssetDepreciationEntry::create([
            'company_id' => $asset->company_id,
            'fixed_asset_id' => $asset->id,
            'period_start_date' => $periodStart->format('Y-m-d'),
            'period_end_date' => $periodEnd->format('Y-m-d'),
            'financial_year' => $financialYear,
            'depreciation_method' => $method,
            'opening_book_value' => $openingBookValue,
            'depreciation_amount' => $depreciationAmount,
            'closing_book_value' => $closingBookValue,
            'accumulated_depreciation_after' => $accumulatedAfter,
            'is_partial_period' => $isPartial,
            'days_in_period' => $activeDays,
            'status' => 'calculated',
            'calculated_at' => Carbon::now(),
            'notes' => $isPartial ? "Pro-rata partial period depreciation ({$activeDays}/{$daysInMonth} days active)" : "Standard monthly depreciation",
        ]);
    }

    /**
     * Post a calculated depreciation entry to the double-entry accounting ledger.
     *
     * Accounting Pattern:
     * - Debit:  Depreciation Expense (5030)  -> Non-cash operational expense
     * - Credit: Accumulated Depreciation (1520) -> Contra-asset balance sheet account
     */
    public static function postDepreciationEntry(AssetDepreciationEntry $entry, User $user): JournalEntry
    {
        return DB::transaction(function () use ($entry, $user) {
            $lockedEntry = AssetDepreciationEntry::where('id', $entry->id)->lockForUpdate()->firstOrFail();

            if ($lockedEntry->status === 'posted' || $lockedEntry->journal_entry_id !== null) {
                throw new \InvalidArgumentException("Depreciation entry #{$lockedEntry->id} has already been posted to the general ledger.");
            }

            $asset = FixedAsset::where('id', $lockedEntry->fixed_asset_id)->lockForUpdate()->firstOrFail();
            $companyId = $asset->company_id;

            // Resolve Depreciation Expense Account (Debit)
            $expenseAccount = $asset->deprExpenseAccount;
            if (! $expenseAccount) {
                $expenseAccount = self::getOrCreateAccount(
                    $companyId,
                    '5030',
                    'Depreciation Expense - Heavy Equipment & Fleet',
                    'expense',
                    'depreciation'
                );
            }

            // Resolve Accumulated Depreciation Account (Credit)
            $accumAccount = $asset->accumulatedDeprAccount;
            if (! $accumAccount) {
                $accumAccount = self::getOrCreateAccount(
                    $companyId,
                    '1520',
                    'Accumulated Depreciation - Plant, Machinery & Fleet',
                    'asset',
                    'fixed_asset'
                );
            }

            $amount = (float) $lockedEntry->depreciation_amount;

            if ($amount <= 0) {
                throw new \InvalidArgumentException("Depreciation amount must be greater than zero to generate a journal entry.");
            }

            // Create Balanced Journal Entry
            $journalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_number' => 'JV-DEPR-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => $lockedEntry->period_end_date,
                'reference_type' => 'asset_depreciation_entries',
                'reference_id' => $lockedEntry->id,
                'narration' => "Depreciation posting for {$asset->name} ({$asset->asset_code}) for period {$lockedEntry->period_start_date->format('d M Y')} to {$lockedEntry->period_end_date->format('d M Y')}",
                'total_amount' => $amount,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // Debit Depreciation Expense
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccount->id,
                'project_id' => $asset->project_id, // Charge to deployed project cost center
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Periodic depreciation for {$asset->name} ({$asset->asset_code})",
            ]);

            // Credit Accumulated Depreciation
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accumAccount->id,
                'project_id' => null,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Accumulated depreciation credit for {$asset->asset_code}",
            ]);

            // Update Depreciation Entry Status
            $lockedEntry->update([
                'journal_entry_id' => $journalEntry->id,
                'status' => 'posted',
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
                'posted_at' => Carbon::now(),
            ]);

            // Update Fixed Asset Master Balances
            $newAccum = round((float) $asset->accumulated_depreciation + $amount, 2);
            $newBookValue = max((float) $asset->residual_value, round((float) $asset->total_capitalized_cost - $newAccum, 2));

            $asset->update([
                'accumulated_depreciation' => $newAccum,
                'current_book_value' => $newBookValue,
            ]);

            // Audit
            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'post_depreciation',
                'auditable_type' => AssetDepreciationEntry::class,
                'auditable_id' => $lockedEntry->id,
                'new_values' => [
                    'asset_code' => $asset->asset_code,
                    'depreciation_amount' => $amount,
                    'journal_entry' => $journalEntry->entry_number,
                    'new_book_value' => $newBookValue,
                    'accumulated_depreciation' => $newAccum,
                ],
            ]);

            return $journalEntry;
        });
    }

    /**
     * Reverse a posted depreciation entry with counter-balancing journal entries.
     */
    public static function reverseDepreciationEntry(AssetDepreciationEntry $entry, string $reversalReason, User $user): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reversalReason, $user) {
            $lockedEntry = AssetDepreciationEntry::where('id', $entry->id)->lockForUpdate()->firstOrFail();

            if ($lockedEntry->status !== 'posted' || ! $lockedEntry->journal_entry_id) {
                throw new \InvalidArgumentException("Depreciation entry #{$lockedEntry->id} is not in posted status.");
            }

            if (strlen(trim($reversalReason)) < 5) {
                throw new \InvalidArgumentException("A mandatory reversal reason of at least 5 characters is required.");
            }

            $asset = FixedAsset::where('id', $lockedEntry->fixed_asset_id)->lockForUpdate()->firstOrFail();
            $originalEntry = JournalEntry::where('id', $lockedEntry->journal_entry_id)->firstOrFail();

            // Mark original entry reversed
            $originalEntry->update([
                'status' => 'reversed',
                'reversed_by' => $user->id,
                'reversed_at' => Carbon::now(),
                'reversal_reason' => $reversalReason,
            ]);

            // Post Reversing Journal
            $reversalJournal = JournalEntry::create([
                'company_id' => $asset->company_id,
                'entry_number' => 'JV-REV-DEPR-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => Carbon::today(),
                'reference_type' => 'asset_depreciation_entries',
                'reference_id' => $lockedEntry->id,
                'narration' => "REVERSAL of {$originalEntry->entry_number}: {$reversalReason}",
                'total_amount' => $originalEntry->total_amount,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            foreach ($originalEntry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversalJournal->id,
                    'account_id' => $line->account_id,
                    'project_id' => $line->project_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => "Reversal: {$line->description}",
                ]);
            }

            // Restore Asset Book Value and Accumulated Depreciation
            $restoredAccum = max(0.0, round((float) $asset->accumulated_depreciation - (float) $lockedEntry->depreciation_amount, 2));
            $restoredBookValue = round((float) $asset->total_capitalized_cost - $restoredAccum, 2);

            $asset->update([
                'accumulated_depreciation' => $restoredAccum,
                'current_book_value' => $restoredBookValue,
            ]);

            $lockedEntry->update([
                'status' => 'reversed',
            ]);

            // Audit
            AuditLog::create([
                'company_id' => $asset->company_id,
                'user_id' => $user->id,
                'action' => 'reverse_depreciation',
                'auditable_type' => AssetDepreciationEntry::class,
                'auditable_id' => $lockedEntry->id,
                'new_values' => [
                    'asset_code' => $asset->asset_code,
                    'reversal_journal' => $reversalJournal->entry_number,
                    'restored_book_value' => $restoredBookValue,
                ],
            ]);

            return $reversalJournal;
        });
    }

    /**
     * Run batch periodic depreciation review across all active assets of a company.
     */
    public static function getPeriodicFleetSummary(int $companyId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $assets = FixedAsset::where('company_id', $companyId)
            ->whereIn('status', ['active', 'idle', 'under_maintenance'])
            ->with(['project', 'loan'])
            ->orderBy('asset_code')
            ->get();

        $items = [];
        $totalPotentialDepreciation = 0.0;
        $totalPostedDepreciation = 0.0;

        foreach ($assets as $asset) {
            $existingEntry = AssetDepreciationEntry::where('fixed_asset_id', $asset->id)
                ->whereDate('period_start_date', $periodStart->toDateString())
                ->whereDate('period_end_date', $periodEnd->toDateString())
                ->first();

            $openingValue = (float) $asset->current_book_value;
            $residualValue = (float) $asset->residual_value;
            $maxDepreciable = max(0.0, round($openingValue - $residualValue, 2));

            $isEligible = ($maxDepreciable > 0.0);
            $calculatedAmount = 0.0;

            if ($existingEntry) {
                $calculatedAmount = (float) $existingEntry->depreciation_amount;
                if ($existingEntry->status === 'posted') {
                    $totalPostedDepreciation += $calculatedAmount;
                } else {
                    $totalPotentialDepreciation += $calculatedAmount;
                }
            } elseif ($isEligible) {
                $method = $asset->depreciation_method;
                $daysInMonth = $periodStart->daysInMonth;
                $assetStart = Carbon::parse($asset->depreciation_start_date ?? $asset->capitalization_date);
                $activeStart = $assetStart->greaterThan($periodStart) ? $assetStart : $periodStart;
                $activeDays = max(1, min($daysInMonth, $activeStart->diffInDays($periodEnd) + 1));
                $isPartial = ($activeDays < $daysInMonth);

                if ($method === 'written_down_value') {
                    $annualRate = (float) ($asset->depreciation_rate_percent ?? 15.0);
                    $fullDepr = round($openingValue * (($annualRate / 100) / 12), 2);
                    $calculatedAmount = $isPartial ? round($fullDepr * ($activeDays / $daysInMonth), 2) : $fullDepr;
                } else {
                    $basis = max(0.0, (float) $asset->total_capitalized_cost - $residualValue);
                    $usefulMonths = max(1, (int) $asset->useful_life_months);
                    $fullDepr = round($basis / $usefulMonths, 2);
                    $calculatedAmount = $isPartial ? round($fullDepr * ($activeDays / $daysInMonth), 2) : $fullDepr;
                }

                $calculatedAmount = min($calculatedAmount, $maxDepreciable);
                $totalPotentialDepreciation += $calculatedAmount;
            }

            $items[] = [
                'asset' => $asset,
                'is_eligible' => $isEligible,
                'ineligible_reason' => $isEligible ? null : 'Salvage residual value reached',
                'entry' => $existingEntry,
                'status' => $existingEntry ? $existingEntry->status : 'uncalculated',
                'depreciation_amount' => $calculatedAmount,
                'opening_book_value' => $openingValue,
                'closing_book_value' => max($residualValue, round($openingValue - $calculatedAmount, 2)),
            ];
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_assets_count' => count($assets),
            'total_potential_depreciation' => $totalPotentialDepreciation,
            'total_posted_depreciation' => $totalPostedDepreciation,
            'items' => $items,
        ];
    }
}
