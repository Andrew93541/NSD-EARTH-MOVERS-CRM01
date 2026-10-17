<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
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
}
