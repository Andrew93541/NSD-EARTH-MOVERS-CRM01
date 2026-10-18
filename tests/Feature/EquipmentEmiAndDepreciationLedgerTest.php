<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AssetDepreciationEntry;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanEmiSchedule;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\EquipmentAccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EquipmentEmiAndDepreciationLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $otherCompany;
    protected User $admin;
    protected User $accountant;
    protected User $siteSupervisor;
    protected Project $project;
    protected Account $bankAccount;
    protected Account $liabilityAccount;
    protected Account $interestAccount;
    protected Account $assetAccount;
    protected Account $accumDeprAccount;
    protected Account $deprExpenseAccount;
    protected Loan $loan;
    protected LoanEmiSchedule $schedule1;
    protected LoanEmiSchedule $schedule2;
    protected FixedAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Primary Company & Roles
        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
        ]);
        session(['current_company_id' => $this->company->id]);

        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'accounting.*', 'assets.*', 'loans.*'],
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site-supervisor',
            'permissions' => ['sites.view', 'attendance.record'],
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->companies()->attach($this->company->id, ['role_id' => $adminRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Chief Accountant',
            'email' => 'accounts@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->accountant->companies()->attach($this->company->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Site Supervisor Dave',
            'email' => 'dave@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->siteSupervisor->companies()->attach($this->company->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        // 2. Project
        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Highway Overpass Project',
            'code' => 'PRJ-HWY-01',
            'status' => 'in_progress',
        ]);

        // 3. Chart of Accounts Setup
        $this->bankAccount = EquipmentAccountingService::getOrCreateAccount(
            $this->company->id,
            '1010',
            'HDFC Current Bank Account',
            'asset',
            'current_asset'
        );

        $this->assetAccount = EquipmentAccountingService::getOrCreateAccount(
            $this->company->id,
            '1510',
            'Plant, Machinery & Heavy Equipment',
            'asset',
            'fixed_asset'
        );

        $this->accumDeprAccount = EquipmentAccountingService::getOrCreateAccount(
            $this->company->id,
            '1520',
            'Accumulated Depreciation - Plant, Machinery & Fleet',
            'asset',
            'fixed_asset'
        );

        $this->liabilityAccount = EquipmentAccountingService::getOrCreateAccount(
            $this->company->id,
            '2010',
            'Equipment Financing Loans & Borrowings',
            'liability',
            'non_current_liability'
        );

        $this->interestAccount = EquipmentAccountingService::getOrCreateAccount(
            $this->company->id,
            '5020',
            'Loan Interest & Financing Charges',
            'expense',
            'finance_cost'
        );

        $this->deprExpenseAccount = EquipmentAccountingService::getOrCreateAccount(
            $this->company->id,
            '5030',
            'Depreciation Expense - Heavy Equipment & Fleet',
            'expense',
            'depreciation'
        );

        // 4. Fixed Asset Setup
        $this->asset = FixedAsset::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'asset_account_id' => $this->assetAccount->id,
            'accumulated_depr_account_id' => $this->accumDeprAccount->id,
            'depr_expense_account_id' => $this->deprExpenseAccount->id,
            'asset_code' => 'AST-EX200-01',
            'name' => 'Tata Hitachi EX200 Excavator',
            'category' => 'excavator',
            'make' => 'Tata Hitachi',
            'model' => 'EX200-LC',
            'serial_number' => 'TH-EX200-98421',
            'registration_number' => 'KA-01-EQ-4421',
            'purchase_date' => '2026-01-01',
            'capitalization_date' => '2026-01-01',
            'depreciation_start_date' => '2026-01-01',
            'acquisition_cost' => 4500000.00,
            'eligible_capitalized_additions' => 0.00,
            'total_capitalized_cost' => 4500000.00,
            'residual_value' => 500000.00, // Depreciable basis = 4,000,000
            'useful_life_years' => 5,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'accumulated_depreciation' => 0.00,
            'current_book_value' => 4500000.00,
            'location' => 'Yard Alpha, Hubli',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // 5. Loan & EMI Schedule Setup
        $this->loan = Loan::create([
            'company_id' => $this->company->id,
            'fixed_asset_id' => $this->asset->id,
            'lender_name' => 'HDFC Equipment Finance Ltd',
            'loan_account_number' => 'HDFC-EQ-882910',
            'principal_amount' => 3000000.00,
            'annual_interest_rate' => 10.50,
            'tenure_months' => 36,
            'monthly_emi' => 97500.00,
            'start_date' => '2026-01-01',
            'end_date' => '2028-12-31',
            'outstanding_principal' => 3000000.00,
            'status' => 'active',
            'asset_description' => 'Tata Hitachi EX200 Excavator',
            'liability_account_id' => $this->liabilityAccount->id,
            'interest_account_id' => $this->interestAccount->id,
        ]);

        $this->schedule1 = LoanEmiSchedule::create([
            'loan_id' => $this->loan->id,
            'installment_number' => 1,
            'due_date' => '2026-02-05',
            'total_emi' => 97500.00,
            'principal_component' => 71250.00,
            'interest_component' => 26250.00,
            'remaining_balance' => 2928750.00,
            'status' => 'pending',
        ]);

        $this->schedule2 = LoanEmiSchedule::create([
            'loan_id' => $this->loan->id,
            'installment_number' => 2,
            'due_date' => '2026-03-05',
            'total_emi' => 97500.00,
            'principal_component' => 71873.44,
            'interest_component' => 25626.56,
            'remaining_balance' => 2856876.56,
            'status' => 'pending',
        ]);

        // 6. Other Company for isolation testing
        $this->otherCompany = Company::create([
            'name' => 'Acme Earthworks Ltd',
            'slug' => 'acme-earthworks',
        ]);
    }

    /**
     * Test 1: EMI principal and interest split creates balanced double-entry journal.
     */
    public function test_emi_principal_and_interest_split_and_balanced_journal_posting(): void
    {
        $this->actingAs($this->accountant);

        $response = $this->post(route('loans.emi.pay', $this->schedule1), [
            'payment_ref' => 'NEFT-HDFC-99124',
            'paid_date' => '2026-02-05',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->schedule1->refresh();
        $this->assertEquals('paid', $this->schedule1->status);
        $this->assertNotNull($this->schedule1->journal_entry_id);

        $journal = JournalEntry::with('lines')->find($this->schedule1->journal_entry_id);
        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(97500.00, (float) $journal->total_amount);

        // Verify Lines: 2 debits, 1 credit
        $lines = $journal->lines;
        $this->assertCount(3, $lines);

        $liabilityLine = $lines->where('account_id', $this->liabilityAccount->id)->first();
        $interestLine = $lines->where('account_id', $this->interestAccount->id)->first();
        $bankLine = $lines->where('account_id', $this->bankAccount->id)->first();

        $this->assertNotNull($liabilityLine);
        $this->assertEquals(71250.00, (float) $liabilityLine->debit);
        $this->assertEquals(0.00, (float) $liabilityLine->credit);

        $this->assertNotNull($interestLine);
        $this->assertEquals(26250.00, (float) $interestLine->debit);
        $this->assertEquals(0.00, (float) $interestLine->credit);

        $this->assertNotNull($bankLine);
        $this->assertEquals(0.00, (float) $bankLine->debit);
        $this->assertEquals(97500.00, (float) $bankLine->credit);

        // Decimal safe exact balance
        $totalDebits = $lines->sum('debit');
        $totalCredits = $lines->sum('credit');
        $this->assertEquals($totalDebits, $totalCredits);
        $this->assertEquals(97500.00, (float) $totalDebits);
    }

    /**
     * Test 2: Duplicate settlement and duplicate posting prevention on retry.
     */
    public function test_duplicate_settlement_and_duplicate_posting_prevention(): void
    {
        $this->actingAs($this->accountant);

        // Initial settlement succeeds
        $this->post(route('loans.emi.pay', $this->schedule1), [
            'payment_ref' => 'NEFT-HDFC-99124',
            'paid_date' => '2026-02-05',
        ]);

        $initialJournalCount = JournalEntry::where('reference_type', 'loan_emi_schedules')
            ->where('reference_id', $this->schedule1->id)
            ->count();
        $this->assertEquals(1, $initialJournalCount);

        // Second retry must be blocked
        $retryResponse = $this->post(route('loans.emi.pay', $this->schedule1), [
            'payment_ref' => 'NEFT-HDFC-99124-RETRY',
            'paid_date' => '2026-02-05',
        ]);

        $retryResponse->assertSessionHas('error');

        $finalJournalCount = JournalEntry::where('reference_type', 'loan_emi_schedules')
            ->where('reference_id', $this->schedule1->id)
            ->count();
        $this->assertEquals(1, $finalJournalCount);
    }

    /**
     * Test 3: Correct loan principal balance after settlement.
     */
    public function test_correct_loan_principal_balance_after_settlement(): void
    {
        $this->actingAs($this->accountant);

        $initialOutstanding = (float) $this->loan->outstanding_principal;
        $this->assertEquals(3000000.00, $initialOutstanding);

        $this->post(route('loans.emi.pay', $this->schedule1), [
            'payment_ref' => 'NEFT-HDFC-99124',
            'paid_date' => '2026-02-05',
        ]);

        $this->loan->refresh();
        $expectedOutstanding = round(3000000.00 - 71250.00, 2);
        $this->assertEquals($expectedOutstanding, (float) $this->loan->outstanding_principal);
        $this->assertEquals(2928750.00, (float) $this->loan->outstanding_principal);
    }

    /**
     * Test 4: Reversal of posted EMI settlement with counter-journal and principal restoration.
     */
    public function test_emi_settlement_reversal_creates_counter_journal_and_restores_principal(): void
    {
        $this->actingAs($this->accountant);

        // 1. Settle EMI #1
        $this->post(route('loans.emi.pay', $this->schedule1), [
            'payment_ref' => 'NEFT-HDFC-99124',
            'paid_date' => '2026-02-05',
        ]);

        $this->schedule1->refresh();
        $originalJournalId = $this->schedule1->journal_entry_id;

        // 2. Reject reversal if reason is too short (< 5 chars)
        $shortReasonResponse = $this->post(route('loans.emi.reverse', $this->schedule1), [
            'reversal_reason' => 'bad',
        ]);
        $shortReasonResponse->assertSessionHasErrors(['reversal_reason']);

        // 3. Perform valid reversal
        $reverseResponse = $this->post(route('loans.emi.reverse', $this->schedule1), [
            'reversal_reason' => 'Bank direct debit bounce advised by lender',
        ]);
        $reverseResponse->assertRedirect();
        $reverseResponse->assertSessionHas('success');

        // Check schedule status reset to pending
        $this->schedule1->refresh();
        $this->assertEquals('pending', $this->schedule1->status);
        $this->assertNull($this->schedule1->journal_entry_id);

        // Check original journal marked reversed
        $originalJournal = JournalEntry::find($originalJournalId);
        $this->assertEquals('reversed', $originalJournal->status);
        $this->assertEquals($this->accountant->id, $originalJournal->reversed_by);

        // Check reversal counter-journal created
        $reversalJournal = JournalEntry::where('narration', 'like', "%REVERSAL of {$originalJournal->entry_number}%")->first();
        $this->assertNotNull($reversalJournal);
        $this->assertEquals(97500.00, (float) $reversalJournal->total_amount);

        // Check flipped lines: Bank is debited, Loan Liability & Interest credited
        $revBankLine = $reversalJournal->lines->where('account_id', $this->bankAccount->id)->first();
        $revLiabLine = $reversalJournal->lines->where('account_id', $this->liabilityAccount->id)->first();
        $revIntLine = $reversalJournal->lines->where('account_id', $this->interestAccount->id)->first();

        $this->assertEquals(97500.00, (float) $revBankLine->debit);
        $this->assertEquals(71250.00, (float) $revLiabLine->credit);
        $this->assertEquals(26250.00, (float) $revIntLine->credit);

        // Check Loan principal restored
        $this->loan->refresh();
        $this->assertEquals(3000000.00, (float) $this->loan->outstanding_principal);
    }

    /**
     * Test 5: Straight-line depreciation calculation and residual salvage value floor.
     */
    public function test_straight_line_depreciation_calculation_and_residual_salvage_floor(): void
    {
        // Capitalized cost: 4,500,000, Residual: 500,000, Useful life: 60 months
        // Depreciable basis = 4,000,000 -> Monthly SLM = 4,000,000 / 60 = 66,666.67
        $periodStart = Carbon::parse('2026-02-01');
        $periodEnd = Carbon::parse('2026-02-28');

        $entry = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            $periodStart,
            $periodEnd,
            $this->admin
        );

        $this->assertEquals(66666.67, (float) $entry->depreciation_amount);
        $this->assertEquals(4500000.00, (float) $entry->opening_book_value);
        $this->assertEquals(4433333.33, (float) $entry->closing_book_value);
        $this->assertEquals('calculated', $entry->status);

        // Test salvage value floor enforcement:
        // Set book value to near residual value (e.g. 520,000, residual 500,000)
        $this->asset->update([
            'current_book_value' => 520000.00,
            'accumulated_depreciation' => 3980000.00,
        ]);

        $periodMarchStart = Carbon::parse('2026-03-01');
        $periodMarchEnd = Carbon::parse('2026-03-31');

        $entryFloor = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            $periodMarchStart,
            $periodMarchEnd,
            $this->admin
        );

        // Max allowable depreciation is 520,000 - 500,000 = 20,000, not the full 66,666.67
        $this->assertEquals(20000.00, (float) $entryFloor->depreciation_amount);
        $this->assertEquals(500000.00, (float) $entryFloor->closing_book_value);

        // When book value == residual value, further calculation must throw
        $this->asset->update(['current_book_value' => 500000.00]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reached its residual salvage value');

        EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
            $this->admin
        );
    }

    /**
     * Test 6: Written-down-value (WDV) depreciation calculation.
     */
    public function test_written_down_value_depreciation_calculation(): void
    {
        $wdvAsset = FixedAsset::create([
            'company_id' => $this->company->id,
            'asset_account_id' => $this->assetAccount->id,
            'accumulated_depr_account_id' => $this->accumDeprAccount->id,
            'depr_expense_account_id' => $this->deprExpenseAccount->id,
            'asset_code' => 'AST-WDV-01',
            'name' => 'JCB 3DX Super Backhoe Loader',
            'category' => 'backhoe',
            'purchase_date' => '2026-01-01',
            'capitalization_date' => '2026-01-01',
            'depreciation_start_date' => '2026-01-01',
            'acquisition_cost' => 3600000.00,
            'total_capitalized_cost' => 3600000.00,
            'residual_value' => 360000.00,
            'useful_life_years' => 8,
            'useful_life_months' => 96,
            'depreciation_method' => 'written_down_value',
            'depreciation_rate_percent' => 15.00, // 15% p.a.
            'accumulated_depreciation' => 0.00,
            'current_book_value' => 3600000.00,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Monthly WDV = 3,600,000 * (15% / 12) = 3,600,000 * 0.0125 = 45,000.00
        $entry = EquipmentAccountingService::calculateDepreciation(
            $wdvAsset,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
            $this->admin
        );

        $this->assertEquals(45000.00, (float) $entry->depreciation_amount);
        $this->assertEquals(3600000.00, (float) $entry->opening_book_value);
        $this->assertEquals(3555000.00, (float) $entry->closing_book_value);
    }

    /**
     * Test 7: Partial-period pro-rata depreciation for mid-period capitalized asset.
     */
    public function test_partial_period_pro_rata_depreciation(): void
    {
        // Capitalized on 16th April 2026 (30 days total, active from 16 to 30 = 15 days active)
        $partialAsset = FixedAsset::create([
            'company_id' => $this->company->id,
            'asset_account_id' => $this->assetAccount->id,
            'accumulated_depr_account_id' => $this->accumDeprAccount->id,
            'depr_expense_account_id' => $this->deprExpenseAccount->id,
            'asset_code' => 'AST-MID-01',
            'name' => 'Mid-Month Roller',
            'category' => 'compactor',
            'purchase_date' => '2026-04-16',
            'capitalization_date' => '2026-04-16',
            'depreciation_start_date' => '2026-04-16',
            'acquisition_cost' => 3000000.00,
            'total_capitalized_cost' => 3000000.00,
            'residual_value' => 0.00,
            'useful_life_years' => 5,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'accumulated_depreciation' => 0.00,
            'current_book_value' => 3000000.00,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Full monthly SLM = 3,000,000 / 60 = 50,000.00
        // Active 15 of 30 days -> 50,000 * (15 / 30) = 25,000.00
        $entry = EquipmentAccountingService::calculateDepreciation(
            $partialAsset,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
            $this->admin
        );

        $this->assertTrue($entry->is_partial_period);
        $this->assertEquals(15, $entry->days_in_period);
        $this->assertEquals(25000.00, (float) $entry->depreciation_amount);
    }

    /**
     * Test 8: Prevention of duplicate depreciation entries for the same asset in the same period.
     */
    public function test_prevention_of_duplicate_depreciation_for_same_asset_and_period(): void
    {
        $periodStart = Carbon::parse('2026-05-01');
        $periodEnd = Carbon::parse('2026-05-31');

        $first = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            $periodStart,
            $periodEnd,
            $this->admin
        );

        $second = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            $periodStart,
            $periodEnd,
            $this->admin
        );

        $this->assertEquals($first->id, $second->id);

        $count = AssetDepreciationEntry::where('fixed_asset_id', $this->asset->id)
            ->whereDate('period_start_date', '2026-05-01')
            ->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 9: Non-cash depreciation journal posting and General Ledger impact.
     */
    public function test_non_cash_depreciation_journal_posting_and_ledger_impact(): void
    {
        $this->actingAs($this->accountant);

        // 1. Calculate depreciation
        $entry = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
            $this->accountant
        );

        // 2. Post via HTTP endpoint
        $postResponse = $this->post(route('assets.depreciation.post', $entry));
        $postResponse->assertRedirect();
        $postResponse->assertSessionHas('success');

        $entry->refresh();
        $this->assertEquals('posted', $entry->status);
        $this->assertNotNull($entry->journal_entry_id);

        // 3. Inspect Journal Entry
        $journal = JournalEntry::with('lines')->find($entry->journal_entry_id);
        $this->assertNotNull($journal);
        $this->assertEquals(66666.67, (float) $journal->total_amount);

        // 4. Verify lines: Debit Depreciation Expense (5030), Credit Accumulated Depr (1520)
        $expenseLine = $journal->lines->where('account_id', $this->deprExpenseAccount->id)->first();
        $accumLine = $journal->lines->where('account_id', $this->accumDeprAccount->id)->first();

        $this->assertNotNull($expenseLine);
        $this->assertEquals(66666.67, (float) $expenseLine->debit);
        $this->assertEquals(0.00, (float) $expenseLine->credit);
        $this->assertEquals($this->project->id, $expenseLine->project_id); // Charged to project cost center

        $this->assertNotNull($accumLine);
        $this->assertEquals(0.00, (float) $accumLine->debit);
        $this->assertEquals(66666.67, (float) $accumLine->credit);

        // 5. Invariant: Bank and Loan accounts MUST NOT be touched
        $bankOrLoanLines = $journal->lines->whereIn('account_id', [$this->bankAccount->id, $this->liabilityAccount->id]);
        $this->assertCount(0, $bankOrLoanLines);

        // 6. Verify Asset Carrying Value Updated
        $this->asset->refresh();
        $this->assertEquals(66666.67, (float) $this->asset->accumulated_depreciation);
        $this->assertEquals(4433333.33, (float) $this->asset->current_book_value);
    }

    /**
     * Test 10: Depreciation reversal creates counter-journal and restores asset carrying value.
     */
    public function test_depreciation_reversal_creates_counter_journal_and_restores_book_value(): void
    {
        $this->actingAs($this->accountant);

        // Calculate and Post
        $entry = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
            $this->accountant
        );
        $journal = EquipmentAccountingService::postDepreciationEntry($entry, $this->accountant);

        // Verify book value reduced
        $this->asset->refresh();
        $this->assertEquals(4433333.33, (float) $this->asset->current_book_value);

        // Reverse with reason
        $reverseResponse = $this->post(route('assets.depreciation.reverse', $entry), [
            'reversal_reason' => 'Audit adjustment due to capitalization date shift',
        ]);
        $reverseResponse->assertRedirect();
        $reverseResponse->assertSessionHas('success');

        $entry->refresh();
        $this->assertEquals('reversed', $entry->status);

        // Original journal marked reversed
        $journal->refresh();
        $this->assertEquals('reversed', $journal->status);

        // Counter journal exists with flipped lines
        $reversalJournal = JournalEntry::where('narration', 'like', "%REVERSAL of {$journal->entry_number}%")->first();
        $this->assertNotNull($reversalJournal);

        $revExpenseLine = $reversalJournal->lines->where('account_id', $this->deprExpenseAccount->id)->first();
        $revAccumLine = $reversalJournal->lines->where('account_id', $this->accumDeprAccount->id)->first();

        $this->assertEquals(66666.67, (float) $revExpenseLine->credit);
        $this->assertEquals(66666.67, (float) $revAccumLine->debit);

        // Asset book value restored
        $this->asset->refresh();
        $this->assertEquals(0.00, (float) $this->asset->accumulated_depreciation);
        $this->assertEquals(4500000.00, (float) $this->asset->current_book_value);
    }

    /**
     * Test 11: General ledger & Trial balance debit/credit equality after EMI & Depreciation.
     */
    public function test_trial_balance_equality_after_emi_and_depreciation(): void
    {
        $this->actingAs($this->accountant);

        // 1. Settle EMI
        EquipmentAccountingService::postEmiPayment($this->schedule1, [
            'paid_date' => '2026-02-05',
            'payment_ref' => 'AUTO-DEBIT-01',
        ], $this->accountant);

        // 2. Post Depreciation
        $deprEntry = EquipmentAccountingService::calculateDepreciation(
            $this->asset,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
            $this->accountant
        );
        EquipmentAccountingService::postDepreciationEntry($deprEntry, $this->accountant);

        // 3. Inspect Trial Balance page
        $tbResponse = $this->get(route('accounting.trial-balance'));
        $tbResponse->assertOk();

        // 4. Verify total debits == total credits in DB for this company
        $totals = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $this->company->id)
            ->where('journal_entries.status', 'posted')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $this->assertNotNull($totals);
        $this->assertEquals($totals->total_debit, $totals->total_credit);
        $this->assertGreaterThan(0, (float) $totals->total_debit);
    }

    /**
     * Test 12: Atomic rollback if settlement or journal posting fails.
     */
    public function test_atomic_rollback_on_posting_failure(): void
    {
        // Force an invalid schedule where components do not sum to total_emi
        $invalidSchedule = LoanEmiSchedule::create([
            'loan_id' => $this->loan->id,
            'installment_number' => 99,
            'due_date' => '2026-12-05',
            'total_emi' => 100000.00,
            'principal_component' => 50000.00,
            'interest_component' => 40000.00, // Sum = 90,000 != 100,000
            'remaining_balance' => 2900000.00,
            'status' => 'pending',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EMI breakdown does not balance');

        try {
            EquipmentAccountingService::postEmiPayment($invalidSchedule, [
                'paid_date' => '2026-12-05',
            ], $this->admin);
        } finally {
            // Assert schedule was not modified
            $invalidSchedule->refresh();
            $this->assertEquals('pending', $invalidSchedule->status);
            $this->assertNull($invalidSchedule->journal_entry_id);

            // Assert no orphaned journal entry created
            $journalExists = JournalEntry::where('reference_type', 'loan_emi_schedules')
                ->where('reference_id', $invalidSchedule->id)
                ->exists();
            $this->assertFalse($journalExists);
        }
    }

    /**
     * Test 13: Unauthorized user rejection on fixed assets and depreciation.
     */
    public function test_unauthorized_user_cannot_view_or_post_assets_or_depreciation(): void
    {
        $this->actingAs($this->siteSupervisor);

        // Site Supervisor cannot view asset register
        $response = $this->get(route('assets.index'));
        $response->assertForbidden();

        // Site Supervisor cannot calculate depreciation
        $deprResponse = $this->post(route('assets.depreciation.calculate', $this->asset), [
            'period_month' => '2026-06',
        ]);
        $deprResponse->assertForbidden();
    }

    /**
     * Test 14: Cross-company tenant isolation on fixed assets and depreciation.
     */
    public function test_cross_company_isolation_on_assets_and_depreciation(): void
    {
        // Create an asset in Company B
        $otherAsset = FixedAsset::create([
            'company_id' => $this->otherCompany->id,
            'asset_code' => 'AST-OTHER-99',
            'name' => 'Foreign Bulldozer',
            'category' => 'dozer',
            'purchase_date' => '2026-01-01',
            'capitalization_date' => '2026-01-01',
            'depreciation_start_date' => '2026-01-01',
            'acquisition_cost' => 5000000.00,
            'total_capitalized_cost' => 5000000.00,
            'residual_value' => 500000.00,
            'useful_life_years' => 5,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'accumulated_depreciation' => 0.00,
            'current_book_value' => 5000000.00,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // User from Company A cannot view or access Company B's asset
        $this->actingAs($this->accountant);

        $response = $this->get(route('assets.show', $otherAsset));
        $response->assertNotFound();
    }

    /**
     * Test 15: Batch periodic fleet depreciation calculation and posting.
     */
    public function test_batch_period_fleet_depreciation_review_and_posting(): void
    {
        $this->actingAs($this->admin);

        // Create a 2nd active asset
        $asset2 = FixedAsset::create([
            'company_id' => $this->company->id,
            'asset_account_id' => $this->assetAccount->id,
            'accumulated_depr_account_id' => $this->accumDeprAccount->id,
            'depr_expense_account_id' => $this->deprExpenseAccount->id,
            'asset_code' => 'AST-CAT-02',
            'name' => 'Caterpillar Motor Grader',
            'category' => 'grader',
            'purchase_date' => '2026-01-01',
            'capitalization_date' => '2026-01-01',
            'depreciation_start_date' => '2026-01-01',
            'acquisition_cost' => 6000000.00,
            'total_capitalized_cost' => 6000000.00,
            'residual_value' => 600000.00, // Depreciable basis = 5,400,000
            'useful_life_years' => 10,
            'useful_life_months' => 120, // 45,000 / month
            'depreciation_method' => 'straight_line',
            'accumulated_depreciation' => 0.00,
            'current_book_value' => 6000000.00,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // View Period Review Page
        $reviewPage = $this->get(route('assets.period-review', ['month' => '2026-07']));
        $reviewPage->assertOk();
        $reviewPage->assertSee('AST-EX200-01');
        $reviewPage->assertSee('AST-CAT-02');

        // Batch Post Depreciation for all active assets for July 2026
        $batchResponse = $this->post(route('assets.period-review.post'), [
            'period_month' => '2026-07',
        ]);

        $batchResponse->assertRedirect();
        $batchResponse->assertSessionHas('success');

        // Verify both assets have posted entries
        $this->asset->refresh();
        $asset2->refresh();

        $this->assertEquals(66666.67, (float) $this->asset->accumulated_depreciation);
        $this->assertEquals(45000.00, (float) $asset2->accumulated_depreciation);

        $postedEntriesCount = AssetDepreciationEntry::where('company_id', $this->company->id)
            ->whereDate('period_start_date', '2026-07-01')
            ->where('status', 'posted')
            ->count();

        $this->assertEquals(2, $postedEntriesCount);
    }
}
