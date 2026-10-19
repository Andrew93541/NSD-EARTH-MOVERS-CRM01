<?php

namespace Tests\Feature\Workflows;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportsAndJournalsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_expense_journals_and_financial_reporting_workflow(): void
    {
        // 1. Setup Company 1
        $company1 = Company::create([
            'name' => 'NSD Earth Movers Ltd',
            'slug' => 'nsd-earth-movers',
            'currency' => 'INR',
            'is_active' => true,
        ]);
        session(['current_company_id' => $company1->id]);

        // Setup Roles
        $accountantRole = Role::create([
            'company_id' => $company1->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['accounting.*', 'finance.*', 'expenses.*'],
            'is_system' => true,
        ]);

        $supervisorRole = Role::create([
            'company_id' => $company1->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['expenses.create', 'expenses.view'],
            'is_system' => false,
        ]);

        $accountant = User::factory()->create(['name' => 'Lead Accountant']);
        $company1->users()->attach($accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $supervisor = User::factory()->create(['name' => 'Field Supervisor']);
        $company1->users()->attach($supervisor->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        // General Ledger Accounts for Company 1
        $bankAccount = Account::create([
            'company_id' => $company1->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $arAccount = Account::create([
            'company_id' => $company1->id,
            'code' => '1020',
            'name' => 'Accounts Receivable',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $apAccount = Account::create([
            'company_id' => $company1->id,
            'code' => '2020',
            'name' => 'Accounts Payable',
            'account_type' => 'liability',
            'sub_type' => 'current_liability',
            'is_active' => true,
        ]);

        $equityAccount = Account::create([
            'company_id' => $company1->id,
            'code' => '3010',
            'name' => 'Share Capital / Owner Equity',
            'account_type' => 'equity',
            'sub_type' => 'equity',
            'is_active' => true,
        ]);

        $revenueAccount = Account::create([
            'company_id' => $company1->id,
            'code' => '4010',
            'name' => 'Civil Contracting Revenue',
            'account_type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'is_active' => true,
        ]);

        $siteExpenseAccount = Account::create([
            'company_id' => $company1->id,
            'code' => '5050',
            'name' => 'Site Operating Expenses',
            'account_type' => 'expense',
            'sub_type' => 'operating_expense',
            'is_active' => true,
        ]);

        $category = ExpenseCategory::create([
            'company_id' => $company1->id,
            'name' => 'Fuel & Lubricants',
            'code' => 'FUEL',
            'is_active' => true,
        ]);

        $project = Project::create([
            'company_id' => $company1->id,
            'name' => 'Highway NH-48 Expansion',
            'code' => 'PRJ-NH48',
            'status' => 'on_track',
            'start_date' => Carbon::now()->subMonths(2),
            'budget' => 2000000.00,
        ]);

        $site = Site::create([
            'company_id' => $company1->id,
            'project_id' => $project->id,
            'name' => 'Channarayapatna Bypass Section',
            'code' => 'STE-CPT-01',
            'status' => 'active',
            'location' => 'Hassan District',
        ]);

        // --- STEP 1: Supervisor Submits Field Expense ---
        $response = $this->actingAs($supervisor)->post(route('expenses.store'), [
            'category_id' => $category->id,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'title' => 'Diesel for Excavator EX-200 (200L)',
            'expense_date' => Carbon::today()->toDateString(),
            'amount' => 18500.00,
            'payment_mode' => 'cash',
            'receipt_reference' => 'PETROL-BUNK-9941',
            'notes' => 'Emergency fuel refill for night grading shift.',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $expense = Expense::where('company_id', $company1->id)->first();
        $this->assertNotNull($expense);
        $this->assertEquals('pending', $expense->status);
        $this->assertEquals(18500.00, (float) $expense->amount);
        $this->assertEquals($supervisor->id, $expense->submitted_by);
        $this->assertNull($expense->approved_by);

        // Verify NO general ledger posting occurred while pending
        $this->assertEquals(0, JournalEntry::where('company_id', $company1->id)->count());

        // --- STEP 2: Supervisor cannot approve expense ---
        $unauthApprove = $this->actingAs($supervisor)->post(route('expenses.approve', $expense));
        $unauthApprove->assertStatus(403);
        $this->assertEquals('pending', $expense->fresh()->status);

        // --- STEP 3: Accountant Approves Field Expense ---
        $approveResponse = $this->actingAs($accountant)->post(route('expenses.approve', $expense));
        $approveResponse->assertRedirect();

        $expense->refresh();
        $this->assertEquals('approved', $expense->status);
        $this->assertEquals($accountant->id, $expense->approved_by);
        $this->assertNotNull($expense->approved_at);

        // Verify automatic balanced double-entry GL posting
        $expenseJournal = JournalEntry::where('company_id', $company1->id)
            ->where('total_amount', 18500.00)
            ->first();
        $this->assertNotNull($expenseJournal);
        $this->assertEquals('posted', $expenseJournal->status);
        $this->assertTrue($expenseJournal->isBalanced());

        // Check journal lines: Dr 5050 (18,500), Cr 1010 (18,500)
        $debitLine = $expenseJournal->lines()->where('account_id', $siteExpenseAccount->id)->first();
        $creditLine = $expenseJournal->lines()->where('account_id', $bankAccount->id)->first();
        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(18500.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);
        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(18500.00, (float) $creditLine->credit);

        // --- STEP 4: Manual Journal Entry Creation with Validation ---
        // 4a. Attempt to post unbalanced journal entry
        $unbalancedResponse = $this->actingAs($accountant)->post(route('accounting.journals.store'), [
            'entry_date' => Carbon::today()->toDateString(),
            'narration' => 'Unbalanced Capital Infusion',
            'lines' => [
                [
                    'account_id' => $bankAccount->id,
                    'debit' => 500000.00,
                    'credit' => 0.00,
                    'description' => 'Bank Deposit',
                ],
                [
                    'account_id' => $equityAccount->id,
                    'debit' => 0.00,
                    'credit' => 400000.00, // Unbalanced by 100,000!
                    'description' => 'Capital',
                ],
            ],
        ]);
        $unbalancedResponse->assertSessionHasErrors('lines');

        // 4b. Post balanced journal entry: Initial Equity Investment
        $balancedResponse = $this->actingAs($accountant)->post(route('accounting.journals.store'), [
            'entry_date' => Carbon::today()->toDateString(),
            'narration' => 'Initial Capital Infusion by Promoters',
            'lines' => [
                [
                    'account_id' => $bankAccount->id,
                    'debit' => 500000.00,
                    'credit' => 0.00,
                    'description' => 'Promoter Capital Credited to Bank Account',
                ],
                [
                    'account_id' => $equityAccount->id,
                    'debit' => 0.00,
                    'credit' => 500000.00,
                    'description' => 'Equity Share Capital',
                ],
            ],
        ]);
        $balancedResponse->assertRedirect(route('accounting.journals'));

        $equityJournal = JournalEntry::where('company_id', $company1->id)
            ->where('total_amount', 500000.00)
            ->first();
        $this->assertNotNull($equityJournal);
        $this->assertEquals('posted', $equityJournal->status);
        $this->assertTrue($equityJournal->isBalanced());
        $this->assertEquals($company1->id, $equityJournal->company_id);

        // Post revenue recognition journal: Dr AR (100,000), Cr Revenue (100,000)
        $revResponse = $this->actingAs($accountant)->post(route('accounting.journals.store'), [
            'entry_date' => Carbon::today()->toDateString(),
            'narration' => 'Interim Civil Work RA Bill #1 Accrual',
            'lines' => [
                [
                    'account_id' => $arAccount->id,
                    'project_id' => $project->id,
                    'debit' => 100000.00,
                    'credit' => 0.00,
                    'description' => 'Accounts Receivable - Client Billing',
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'project_id' => $project->id,
                    'debit' => 0.00,
                    'credit' => 100000.00,
                    'description' => 'Recognized Contracting Revenue',
                ],
            ],
        ]);
        $revResponse->assertRedirect(route('accounting.journals'));

        $revJournal = JournalEntry::where('company_id', $company1->id)
            ->where('total_amount', 100000.00)
            ->first();
        $this->assertNotNull($revJournal);

        // --- STEP 5: Reversal of Journal Entry ---
        // Post a temporary adjustment journal to test reversal
        $adjResponse = $this->actingAs($accountant)->post(route('accounting.journals.store'), [
            'entry_date' => Carbon::today()->toDateString(),
            'narration' => 'Accidental Suspense Adjustment',
            'lines' => [
                [
                    'account_id' => $bankAccount->id,
                    'debit' => 5000.00,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $apAccount->id,
                    'debit' => 0.00,
                    'credit' => 5000.00,
                ],
            ],
        ]);
        $adjResponse->assertRedirect(route('accounting.journals'));

        $adjJournal = JournalEntry::where('company_id', $company1->id)
            ->where('total_amount', 5000.00)
            ->first();
        $this->assertNotNull($adjJournal);

        // Reverse the adjustment journal entry
        $reverseResponse = $this->actingAs($accountant)->post(route('accounting.journals.reverse', $adjJournal), [
            'reversal_reason' => 'Incorrect adjustment entry, reversed per accounting audit.',
        ]);
        $reverseResponse->assertRedirect(route('accounting.journals'));

        $adjJournal->refresh();
        $this->assertEquals('reversed', $adjJournal->status);
        $this->assertEquals($accountant->id, $adjJournal->reversed_by);
        $this->assertNotNull($adjJournal->reversed_at);

        // Counter entry created
        $counterJournal = JournalEntry::where('reference_type', 'journal_entries_reversal')
            ->where('reference_id', $adjJournal->id)
            ->first();
        $this->assertNotNull($counterJournal);
        $this->assertEquals('posted', $counterJournal->status);
        $this->assertEquals(5000.00, (float) $counterJournal->total_amount);
        $this->assertTrue($counterJournal->isBalanced());

        // Check inverted lines: Dr AP (5,000), Cr Bank (5,000)
        $counterApLine = $counterJournal->lines()->where('account_id', $apAccount->id)->first();
        $counterBankLine = $counterJournal->lines()->where('account_id', $bankAccount->id)->first();
        $this->assertEquals(5000.00, (float) $counterApLine->debit);
        $this->assertEquals(5000.00, (float) $counterBankLine->credit);

        // Verify cannot reverse an already reversed journal entry
        $reReversal = $this->actingAs($accountant)->post(route('accounting.journals.reverse', $adjJournal), [
            'reversal_reason' => 'Trying to reverse again',
        ]);
        $reReversal->assertSessionHasErrors('reversal_reason');

        // --- STEP 6: Multi-Tenant Isolation ---
        // Setup Company 2 and a separate journal entry
        $company2 = Company::create([
            'name' => 'Independent Competitor Corp',
            'slug' => 'competitor-corp',
            'currency' => 'INR',
            'is_active' => true,
        ]);
        $c2Account = Account::create([
            'company_id' => $company2->id,
            'code' => '1010',
            'name' => 'Competitor Bank',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);
        $c2Equity = Account::create([
            'company_id' => $company2->id,
            'code' => '3010',
            'name' => 'Competitor Equity',
            'account_type' => 'equity',
            'sub_type' => 'equity',
            'is_active' => true,
        ]);

        $c2Journal = JournalEntry::create([
            'company_id' => $company2->id,
            'entry_number' => 'JV-C2-001',
            'entry_date' => Carbon::today(),
            'narration' => 'Company 2 Private Transaction',
            'total_amount' => 999999.00,
            'status' => 'posted',
            'created_by' => $accountant->id,
        ]);
        \App\Models\JournalEntryLine::create([
            'journal_entry_id' => $c2Journal->id,
            'account_id' => $c2Account->id,
            'debit' => 999999.00,
            'credit' => 0.00,
        ]);
        \App\Models\JournalEntryLine::create([
            'journal_entry_id' => $c2Journal->id,
            'account_id' => $c2Equity->id,
            'debit' => 0.00,
            'credit' => 999999.00,
        ]);
        JournalEntry::lockAfterCreation($c2Journal->id);

        // Ensure session remains Company 1
        session(['current_company_id' => $company1->id]);

        // --- STEP 7: Verify Trial Balance Report ---
        $tbResponse = $this->actingAs($accountant)->get(route('accounting.trial-balance'));
        $tbResponse->assertOk();
        $tbResponse->assertViewHas('totalDebits');
        $tbResponse->assertViewHas('totalCredits');

        $totalDebits = $tbResponse->viewData('totalDebits');
        $totalCredits = $tbResponse->viewData('totalCredits');

        // Total Debits MUST strictly equal Total Credits
        $this->assertEquals(0, bccomp((string) $totalDebits, (string) $totalCredits, 2));

        // Company 2 figures must not leak into Company 1 trial balance
        $rows = collect($tbResponse->viewData('rows'));
        $c2Present = $rows->contains(fn ($r) => $r['account']->company_id === $company2->id);
        $this->assertFalse($c2Present, 'Company 2 accounts must never appear in Company 1 trial balance');

        // --- STEP 8: Verify Profit & Loss Report ---
        $plResponse = $this->actingAs($accountant)->get(route('accounting.profit-loss'));
        $plResponse->assertOk();
        $plResponse->assertViewHas('totalRevenue');
        $plResponse->assertViewHas('totalExpenses');
        $plResponse->assertViewHas('netProfit');

        $totalRevenue = (float) $plResponse->viewData('totalRevenue');
        $totalExpenses = (float) $plResponse->viewData('totalExpenses');
        $netProfit = (float) $plResponse->viewData('netProfit');

        // Revenue: 100,000 (from civil contracting)
        // Expenses: 18,500 (from approved site diesel expense)
        // Net Profit: 100,000 - 18,500 = 81,500
        $this->assertEquals(100000.00, $totalRevenue);
        $this->assertEquals(18500.00, $totalExpenses);
        $this->assertEquals(81500.00, $netProfit);

        // --- STEP 9: Verify Balance Sheet Report ---
        $bsResponse = $this->actingAs($accountant)->get(route('accounting.balance-sheet'));
        $bsResponse->assertOk();
        $bsResponse->assertViewHas('totalAssets');
        $bsResponse->assertViewHas('totalLiabilities');
        $bsResponse->assertViewHas('totalEquity');
        $bsResponse->assertViewHas('cumulativeRetainedEarnings');
        $bsResponse->assertViewHas('totalLiabilitiesAndEquity');

        $totalAssets = (float) $bsResponse->viewData('totalAssets');
        $totalLiabilitiesAndEquity = (float) $bsResponse->viewData('totalLiabilitiesAndEquity');
        $cumulativeRetained = (float) $bsResponse->viewData('cumulativeRetainedEarnings');

        // Retained earnings equals net profit (81,500)
        $this->assertEquals(81500.00, $cumulativeRetained);

        // Assets: Bank (500,000 capital - 18,500 expense = 481,500) + AR (100,000) = 581,500
        // Equity + Retained: 500,000 + 81,500 = 581,500
        // Accounting Equation: Total Assets == Total Liabilities + Total Equity
        $this->assertEquals($totalAssets, $totalLiabilitiesAndEquity);
    }
}
