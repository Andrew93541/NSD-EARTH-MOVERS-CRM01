<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceiptAllocationAndFinancialStatementsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $accountant;
    protected Client $client;
    protected Project $project;
    protected ClientInvoice $invoice1;
    protected ClientInvoice $invoice2;
    protected Account $bankAccount;
    protected Account $revenueAccount;
    protected Account $expenseAccount;
    protected Account $equityAccount;
    protected Account $receivableAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['*'],
        ]);

        $this->accountant = User::create([
            'name' => 'Ramesh Chander',
            'email' => 'finance@nsdcrm.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->company->users()->attach($this->accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active']);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'State Highway Development Authority',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'State Highway Bypass 44',
            'code' => 'SH-44',
            'status' => 'active',
        ]);

        $this->invoice1 = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-001',
            'issue_date' => Carbon::now()->subDays(10),
            'due_date' => Carbon::now()->addDays(20),
            'subtotal' => 100000.00,
            'total_amount' => 100000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->accountant->id,
        ]);

        $this->invoice2 = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-002',
            'issue_date' => Carbon::now()->subDays(5),
            'due_date' => Carbon::now()->addDays(25),
            'subtotal' => 200000.00,
            'total_amount' => 200000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->accountant->id,
        ]);

        // Standard accounts for double-entry testing
        $this->bankAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '10100',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
        ]);

        $this->receivableAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '10200',
            'name' => 'Accounts Receivable (Clients)',
            'account_type' => 'asset',
        ]);

        $this->equityAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '30100',
            'name' => 'Share Capital / Partners Equity',
            'account_type' => 'equity',
        ]);

        $this->revenueAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '40100',
            'name' => 'Civil Earthmoving Contract Revenue',
            'account_type' => 'revenue',
        ]);

        $this->expenseAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '50100',
            'name' => 'Heavy Machinery Fuel & Diesel',
            'account_type' => 'expense',
        ]);
    }

    public function test_can_allocate_unallocated_receipt_across_multiple_invoices(): void
    {
        // Client pays lump sum of ₹2,50,000 against Invoice 1 (₹1,00,000) and Invoice 2 (₹2,00,000)
        $payment = InvoicePayment::create([
            'company_id' => $this->company->id,
            'client_invoice_id' => $this->invoice1->id,
            'receipt_number' => 'RCP-2026-LUMP1',
            'payment_date' => Carbon::today()->toDateString(),
            'amount' => 250000.00,
            'payment_mode' => 'rtgs_neft',
            'reference_number' => 'UTR9988223311',
            'recorded_by' => $this->accountant->id,
        ]);

        // Before allocation, entire ₹2,50,000 is unallocated balance
        $this->assertEquals(250000.00, $payment->unallocatedBalance());

        // Allocate ₹1,00,000 to Invoice 1 (full settlement) and ₹1,50,000 to Invoice 2 (partial settlement)
        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('billing.payments.allocate', $payment), [
                'allocations' => [
                    ['invoice_id' => $this->invoice1->id, 'amount' => 100000.00],
                    ['invoice_id' => $this->invoice2->id, 'amount' => 150000.00],
                ],
            ]);

        $response->assertSessionHas('success');

        // Verify Invoice 1 is fully paid
        $this->invoice1->refresh();
        $this->assertEquals(100000.00, (float) $this->invoice1->paid_amount);
        $this->assertEquals('paid', $this->invoice1->status);

        // Verify Invoice 2 is partially paid with ₹50,000 remaining
        $this->invoice2->refresh();
        $this->assertEquals(150000.00, (float) $this->invoice2->paid_amount);
        $this->assertEquals('partially_paid', $this->invoice2->status);
        $this->assertEquals(50000.00, $this->invoice2->balanceDue());

        // Verify receipt unallocated balance is now 0.00
        $this->assertEquals(0.00, $payment->fresh()->unallocatedBalance());
    }

    public function test_over_allocation_beyond_receipt_balance_is_strictly_rejected(): void
    {
        $payment = InvoicePayment::create([
            'company_id' => $this->company->id,
            'client_invoice_id' => $this->invoice1->id,
            'receipt_number' => 'RCP-2026-LUMP2',
            'payment_date' => Carbon::today()->toDateString(),
            'amount' => 50000.00,
            'payment_mode' => 'rtgs_neft',
            'recorded_by' => $this->accountant->id,
        ]);

        // Attempting to allocate ₹80,000 when receipt only has ₹50,000 must fail
        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('billing.payments.allocate', $payment), [
                'allocations' => [
                    ['invoice_id' => $this->invoice1->id, 'amount' => 80000.00],
                ],
            ]);

        $response->assertSessionHasErrors('allocations');
        $this->assertEquals(0.00, (float) $this->invoice1->fresh()->paid_amount);
    }

    public function test_profit_and_loss_and_balance_sheet_statements(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. Post Revenue JV: Debit Receivable 500,000, Credit Revenue 500,000
        $jv1 = JournalEntry::create([
            'company_id' => $this->company->id,
            'entry_number' => 'JV-2026-REV01',
            'entry_date' => $today,
            'narration' => 'Billing revenue recognition for excavation works',
            'total_amount' => 500000.00,
            'status' => 'posted',
            'created_by' => $this->accountant->id,
        ]);
        JournalEntryLine::create(['journal_entry_id' => $jv1->id, 'account_id' => $this->receivableAccount->id, 'debit' => 500000.00, 'credit' => 0.00]);
        JournalEntryLine::create(['journal_entry_id' => $jv1->id, 'account_id' => $this->revenueAccount->id, 'debit' => 0.00, 'credit' => 500000.00]);

        // 2. Post Expense JV: Debit Diesel Expense 200,000, Credit Bank 200,000
        $jv2 = JournalEntry::create([
            'company_id' => $this->company->id,
            'entry_number' => 'JV-2026-EXP01',
            'entry_date' => $today,
            'narration' => 'Diesel procurement for earthmoving fleet',
            'total_amount' => 200000.00,
            'status' => 'posted',
            'created_by' => $this->accountant->id,
        ]);
        JournalEntryLine::create(['journal_entry_id' => $jv2->id, 'account_id' => $this->expenseAccount->id, 'debit' => 200000.00, 'credit' => 0.00]);
        JournalEntryLine::create(['journal_entry_id' => $jv2->id, 'account_id' => $this->bankAccount->id, 'debit' => 0.00, 'credit' => 200000.00]);

        // 3. Post Capital JV: Debit Bank 1,000,000, Credit Equity 1,000,000
        $jv3 = JournalEntry::create([
            'company_id' => $this->company->id,
            'entry_number' => 'JV-2026-CAP01',
            'entry_date' => $today,
            'narration' => 'Initial capital introduction',
            'total_amount' => 1000000.00,
            'status' => 'posted',
            'created_by' => $this->accountant->id,
        ]);
        JournalEntryLine::create(['journal_entry_id' => $jv3->id, 'account_id' => $this->bankAccount->id, 'debit' => 1000000.00, 'credit' => 0.00]);
        JournalEntryLine::create(['journal_entry_id' => $jv3->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 1000000.00]);

        // Test P&L statement
        $plResponse = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('accounting.profit-loss'));

        $plResponse->assertStatus(200);
        $plResponse->assertSee('Statement of Profit & Loss', false);
        $plResponse->assertSee('500,000.00'); // Revenue
        $plResponse->assertSee('200,000.00'); // Expense
        $plResponse->assertSee('300,000.00'); // Net Profit = 500k - 200k = 300k

        // Test Balance Sheet statement
        $bsResponse = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('accounting.balance-sheet'));

        $bsResponse->assertStatus(200);
        $bsResponse->assertSee('Statement of Financial Position', false);
        $bsResponse->assertSee('Exact Balance', false);
        // Bank Net = 1,000,000 (Dr) - 200,000 (Cr) = 800,000. Receivable = 500,000. Total Assets = 1,300,000.
        // Equity = 1,000,000. Retained Earnings = 300,000. Total Equity = 1,300,000.
        $bsResponse->assertSee('1,300,000.00');
    }
}
