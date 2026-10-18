<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DoubleEntryAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected Company $company;
    protected Account $bankAccount;
    protected Account $receivablesAccount;
    protected Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['*'],
        ]);

        $this->accountant = User::create([
            'name' => 'Senior Accountant',
            'email' => 'accounts@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->accountant->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->bankAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $this->receivablesAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '1020',
            'name' => 'Accounts Receivable',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '4010',
            'name' => 'Contracting Revenue',
            'account_type' => 'revenue',
            'is_active' => true,
        ]);
    }

    public function test_can_post_balanced_journal_entry(): void
    {
        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/accounting/journals', [
                'entry_date' => Carbon::today()->format('Y-m-d'),
                'narration' => 'Initial customer settlement received in HDFC Bank',
                'lines' => [
                    [
                        'account_id' => $this->bankAccount->id,
                        'debit' => 250000.00,
                        'credit' => 0.00,
                        'description' => 'Bank debit',
                    ],
                    [
                        'account_id' => $this->receivablesAccount->id,
                        'debit' => 0.00,
                        'credit' => 250000.00,
                        'description' => 'Clear receivables',
                    ],
                ],
            ]);

        $response->assertRedirect('/accounting/journals');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'total_amount' => 250000.00,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->bankAccount->id,
            'debit' => 250000.00,
            'credit' => 0.00,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->receivablesAccount->id,
            'debit' => 0.00,
            'credit' => 250000.00,
        ]);
    }

    public function test_rejects_unbalanced_journal_entry(): void
    {
        // Total debit 250,000 vs total credit 200,000 (imbalance of 50,000)
        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/accounting/journals', [
                'entry_date' => Carbon::today()->format('Y-m-d'),
                'narration' => 'Erroneous unbalanced entry',
                'lines' => [
                    [
                        'account_id' => $this->bankAccount->id,
                        'debit' => 250000.00,
                        'credit' => 0.00,
                    ],
                    [
                        'account_id' => $this->receivablesAccount->id,
                        'debit' => 0.00,
                        'credit' => 200000.00,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('lines');

        $this->assertDatabaseMissing('journal_entries', [
            'narration' => 'Erroneous unbalanced entry',
        ]);
    }

    public function test_trial_balance_renders_with_equal_debits_and_credits(): void
    {
        // First post a balanced journal entry
        $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/accounting/journals', [
                'entry_date' => Carbon::today()->format('Y-m-d'),
                'narration' => 'Initial capital inflow',
                'lines' => [
                    [
                        'account_id' => $this->bankAccount->id,
                        'debit' => 1000000.00,
                        'credit' => 0.00,
                    ],
                    [
                        'account_id' => $this->revenueAccount->id,
                        'debit' => 0.00,
                        'credit' => 1000000.00,
                    ],
                ],
            ]);

        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/accounting/trial-balance');

        $response->assertStatus(200);
        $response->assertSee('Trial Balance');
        $response->assertSee('HDFC Current Bank Account');
        $response->assertSee('1,000,000.00');
    }

    public function test_posted_journal_entry_cannot_be_deleted_via_model(): void
    {
        $this->postSampleJournal();

        $entry = JournalEntry::where('status', 'posted')->first();
        $this->assertNotNull($entry);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("cannot be deleted. Financial records are permanent and immutable");
        $entry->delete();
    }

    public function test_posted_journal_header_cannot_be_modified_via_model(): void
    {
        $this->postSampleJournal();

        $entry = JournalEntry::where('status', 'posted')->first();
        $this->assertNotNull($entry);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("immutable and cannot be modified");
        $entry->update(['total_amount' => 999999.00]);
    }

    public function test_posted_journal_line_cannot_be_added_after_creation(): void
    {
        $this->postSampleJournal();

        $entry = JournalEntry::where('status', 'posted')->first();
        $this->assertNotNull($entry);

        // Ensure creation lock is active
        JournalEntry::lockAfterCreation($entry->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("immutable. New lines cannot be added to a posted or reversed entry");

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->bankAccount->id,
            'debit' => 50000.00,
            'credit' => 0.00,
            'description' => 'Unsafe direct line injection attempt',
        ]);
    }

    public function test_posted_journal_line_cannot_be_modified_via_model(): void
    {
        $this->postSampleJournal();

        $line = JournalEntryLine::latest()->first();
        $this->assertNotNull($line);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("immutable and cannot be modified");
        $line->update(['debit' => 999999.00]);
    }

    public function test_posted_journal_line_cannot_be_deleted_via_model(): void
    {
        $this->postSampleJournal();

        $line = JournalEntryLine::latest()->first();
        $this->assertNotNull($line);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("cannot be deleted. Financial records are permanent and immutable");
        $line->delete();
    }

    public function test_authorized_accountant_can_reverse_posted_journal_with_counter_entry(): void
    {
        $this->postSampleJournal(150000.00);

        $original = JournalEntry::where('status', 'posted')
            ->where('total_amount', 150000.00)
            ->firstOrFail();

        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/accounting/journals/{$original->id}/reverse", [
                'reversal_reason' => 'Erroneous billing entry posted in error; cancelling with counter voucher',
            ]);

        $response->assertRedirect('/accounting/journals');
        $response->assertSessionHas('success');

        $original->refresh();
        $this->assertEquals('reversed', $original->status);
        $this->assertEquals($this->accountant->id, $original->reversed_by);
        $this->assertNotNull($original->reversed_at);
        $this->assertEquals('Erroneous billing entry posted in error; cancelling with counter voucher', $original->reversal_reason);

        // Counter entry verification
        $counter = JournalEntry::where('reference_type', 'journal_entries_reversal')
            ->where('reference_id', $original->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals('posted', $counter->status);
        $this->assertEquals(150000.00, (float) $counter->total_amount);
        $this->assertTrue($counter->isBalanced());

        // Debits and credits should be inverted
        $origBankLine = $original->lines()->where('account_id', $this->bankAccount->id)->first();
        $counterBankLine = $counter->lines()->where('account_id', $this->bankAccount->id)->first();
        $this->assertEquals($origBankLine->debit, $counterBankLine->credit);
        $this->assertEquals($origBankLine->credit, $counterBankLine->debit);

        // AuditLog verification
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->accountant->id,
            'action' => 'reverse_journal_entry',
            'auditable_type' => JournalEntry::class,
            'auditable_id' => $original->id,
        ]);
    }

    public function test_unauthorized_user_cannot_post_or_reverse_journals(): void
    {
        $this->postSampleJournal();
        $entry = JournalEntry::where('status', 'posted')->firstOrFail();

        // Create user with site supervisor role (no accounting permission)
        $siteRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'attendance.record'],
        ]);

        $siteWorker = User::create([
            'name' => 'Site Worker',
            'email' => 'worker@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($siteWorker->id, [
            'role_id' => $siteRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Attempting to post journal without permission
        $postResponse = $this->actingAs($siteWorker)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/accounting/journals', [
                'entry_date' => Carbon::today()->format('Y-m-d'),
                'narration' => 'Unauthorized journal attempt',
                'lines' => [
                    ['account_id' => $this->bankAccount->id, 'debit' => 100, 'credit' => 0],
                    ['account_id' => $this->receivablesAccount->id, 'debit' => 0, 'credit' => 100],
                ],
            ]);

        $postResponse->assertStatus(403);

        // Attempting to reverse journal without permission
        $reverseResponse = $this->actingAs($siteWorker)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/accounting/journals/{$entry->id}/reverse", [
                'reversal_reason' => 'Unauthorized reversal attempt',
            ]);

        $reverseResponse->assertStatus(403);
    }

    public function test_duplicate_reversal_is_blocked(): void
    {
        $this->postSampleJournal(75000.00);
        $entry = JournalEntry::where('status', 'posted')->where('total_amount', 75000.00)->firstOrFail();

        // First reversal succeeds
        $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/accounting/journals/{$entry->id}/reverse", [
                'reversal_reason' => 'First legitimate reversal of error voucher',
            ]);

        $entry->refresh();
        $this->assertEquals('reversed', $entry->status);

        // Second reversal must be rejected
        $secondResponse = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/accounting/journals/{$entry->id}/reverse", [
                'reversal_reason' => 'Second duplicate reversal attempt',
            ]);

        $secondResponse->assertSessionHasErrors('reversal_reason');

        // Direct model update on an already-reversed entry must throw DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("is immutable and cannot be modified or reversed again");
        $entry->update(['narration' => 'Illegal tamper attempt on reversed entry']);
    }

    public function test_reversal_requires_minimum_five_character_reason(): void
    {
        $this->postSampleJournal(20000.00);
        $entry = JournalEntry::where('status', 'posted')->where('total_amount', 20000.00)->firstOrFail();

        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/accounting/journals/{$entry->id}/reverse", [
                'reversal_reason' => 'bad', // less than 5 characters
            ]);

        $response->assertSessionHasErrors('reversal_reason');
        $this->assertEquals('posted', $entry->fresh()->status);
    }

    public function test_subsystem_journal_entries_blocked_from_generic_reversal(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'entry_number' => 'JV-SCB-TEST-001',
            'entry_date' => Carbon::today(),
            'reference_type' => 'subcontractor_bills',
            'reference_id' => 99,
            'narration' => 'Subcontractor RA Bill Certification',
            'total_amount' => 50000.00,
            'status' => 'posted',
            'created_by' => $this->accountant->id,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->bankAccount->id,
            'debit' => 50000.00,
            'credit' => 0.00,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->revenueAccount->id,
            'debit' => 0.00,
            'credit' => 50000.00,
        ]);

        JournalEntry::lockAfterCreation($entry->id);

        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/accounting/journals/{$entry->id}/reverse", [
                'reversal_reason' => 'Attempting to reverse via generic accounting route',
            ]);

        $response->assertSessionHasErrors('reversal_reason');
        $this->assertStringContainsString('Subcontractor Bill reversal workflow', session('errors')->first('reversal_reason'));
    }

    public function test_draft_journal_can_be_edited_and_deleted_prior_to_posting(): void
    {
        $draft = JournalEntry::create([
            'company_id' => $this->company->id,
            'entry_number' => 'JV-DRAFT-001',
            'entry_date' => Carbon::today(),
            'narration' => 'Draft adjustment voucher',
            'total_amount' => 10000.00,
            'status' => 'draft',
            'created_by' => $this->accountant->id,
        ]);

        $line1 = JournalEntryLine::create([
            'journal_entry_id' => $draft->id,
            'account_id' => $this->bankAccount->id,
            'debit' => 10000.00,
            'credit' => 0.00,
        ]);

        $line2 = JournalEntryLine::create([
            'journal_entry_id' => $draft->id,
            'account_id' => $this->receivablesAccount->id,
            'debit' => 0.00,
            'credit' => 10000.00,
        ]);

        // Draft lines can be updated
        $line1->update(['debit' => 15000.00]);
        $this->assertEquals(15000.00, (float) $line1->fresh()->debit);

        // Draft header can be updated
        $draft->update(['narration' => 'Updated draft narration']);
        $this->assertEquals('Updated draft narration', $draft->fresh()->narration);

        // Draft lines can be deleted
        $line2->delete();
        $this->assertDatabaseMissing('journal_entry_lines', ['id' => $line2->id]);

        // Draft entry can be deleted
        $draft->delete();
        $this->assertDatabaseMissing('journal_entries', ['id' => $draft->id]);
    }

    /**
     * Helper to post a standard balanced sample journal entry.
     */
    protected function postSampleJournal(float $amount = 250000.00): void
    {
        $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/accounting/journals', [
                'entry_date' => Carbon::today()->format('Y-m-d'),
                'narration' => "Balanced sample voucher for ₹{$amount}",
                'lines' => [
                    [
                        'account_id' => $this->bankAccount->id,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'description' => 'Debit leg',
                    ],
                    [
                        'account_id' => $this->receivablesAccount->id,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'description' => 'Credit leg',
                    ],
                ],
            ]);
    }
}
