<?php

namespace Tests\Feature\Workflows;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBillingAndReceiptAllocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_client_billing_approval_payment_and_project_financials(): void
    {
        // 1. Setup Company
        $company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
            'currency' => 'INR',
            'is_active' => true,
        ]);
        session(['current_company_id' => $company->id]);

        // Roles
        $accountantRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['billing.*', 'finance.*', 'projects.view'],
            'is_system' => true,
        ]);

        $accountant = User::factory()->create(['name' => 'Finance Executive']);
        $company->users()->attach($accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        // General Ledger Accounts
        $bankAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $arAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1020',
            'name' => 'Accounts Receivable (Clients)',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $retentionAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1025',
            'name' => 'Retention Money Withheld by Clients',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $revenueAccount = Account::create([
            'company_id' => $company->id,
            'code' => '4010',
            'name' => 'Civil Contracting Revenue',
            'account_type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'is_active' => true,
        ]);

        // Client & Project
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Bangalore Metro Rail Corporation Ltd (BMRCL)',
            'email' => 'contracts@bmrcl.in',
            'phone' => '08022969300',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'code' => 'PRJ-BMRCL-UG',
            'name' => 'BMRCL Underground Reach 6',
            'contract_value' => 5000000.00,
            'approved_budget' => 4200000.00,
            'status' => 'on_track',
        ]);

        // 2. Accountant creates RA Bill #1
        // Subtotal: 1,000,000
        // GST: 18% (9% CGST 90,000 + 9% SGST 90,000)
        // Retention: 5% of subtotal = 50,000
        // Net Total: (1,000,000 + 180,000) - 50,000 = 1,130,000
        $invoiceResponse = $this->actingAs($accountant)->post(route('billing.store'), [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'bill_type' => 'progress_ra',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'subtotal' => 1000000.00,
            'tax_rate' => 18.00,
            'retention_rate_percent' => 5.00,
            'notes' => 'RA Bill 01 - Earth excavation and piling works',
        ]);

        $invoiceResponse->assertRedirect();
        $invoice = ClientInvoice::where('project_id', $project->id)->firstOrFail();
        $this->assertEquals('submitted', $invoice->status);
        $this->assertEquals('1000000.00', $invoice->subtotal);
        $this->assertEquals('90000.00', $invoice->cgst_amount);
        $this->assertEquals('90000.00', $invoice->sgst_amount);
        $this->assertEquals('50000.00', $invoice->retention_amount);
        $this->assertEquals('1130000.00', $invoice->total_amount);
        $this->assertEquals('0.00', $invoice->paid_amount);

        // 3. Approve Invoice and Verify Balanced Revenue Accrual Journal
        $approveResponse = $this->actingAs($accountant)->post(route('billing.approve', $invoice));
        $approveResponse->assertRedirect();
        $this->assertEquals('approved', $invoice->fresh()->status);

        $revenueJournal = JournalEntry::where('company_id', $company->id)
            ->where('narration', 'like', '%Revenue recognized%')
            ->first();
        $this->assertNotNull($revenueJournal);
        $this->assertEquals('1180000.00', $revenueJournal->total_amount);
        $this->assertTrue($revenueJournal->isBalanced());

        // Verify Dr 1020 (1,130,000), Dr 1025 (50,000), Cr 4010 (1,180,000)
        $drAr = $revenueJournal->lines()->where('account_id', $arAccount->id)->first();
        $drRetention = $revenueJournal->lines()->where('account_id', $retentionAccount->id)->first();
        $crRevenue = $revenueJournal->lines()->where('account_id', $revenueAccount->id)->first();

        $this->assertEquals('1130000.00', $drAr->debit);
        $this->assertEquals('50000.00', $drRetention->debit);
        $this->assertEquals('1180000.00', $crRevenue->credit);

        // 4. Record Partial Payment: ₹600,000 from Client
        $pay1Response = $this->actingAs($accountant)->post(route('billing.payments.store', $invoice), [
            'payment_date' => '2026-10-15',
            'amount' => 600000.00,
            'payment_mode' => 'rtgs_neft',
            'reference_number' => 'BMRCL-RTGS-9912',
            'notes' => 'First milestone release',
        ]);

        $pay1Response->assertRedirect();
        $freshInv = $invoice->fresh();
        $this->assertEquals('partially_paid', $freshInv->status);
        $this->assertEquals('600000.00', $freshInv->paid_amount);
        $this->assertEquals(530000.00, $freshInv->balanceDue());

        // Verify GL Payment Journal: Dr 1010 Bank (600,000), Cr 1020 AR (600,000)
        $paymentJournal = JournalEntry::where('company_id', $company->id)
            ->where('narration', 'like', '%Receipt collected%')
            ->first();
        $this->assertNotNull($paymentJournal);
        $this->assertEquals('600000.00', $paymentJournal->total_amount);
        $this->assertTrue($paymentJournal->isBalanced());

        $drBank = $paymentJournal->lines()->where('account_id', $bankAccount->id)->first();
        $crAr = $paymentJournal->lines()->where('account_id', $arAccount->id)->first();
        $this->assertEquals('600000.00', $drBank->debit);
        $this->assertEquals('600000.00', $crAr->credit);

        // 5. Overpayment Rejection (attempting to pay 600,000 more when balance is 530,000)
        $overpayResponse = $this->actingAs($accountant)->post(route('billing.payments.store', $invoice), [
            'payment_date' => '2026-10-20',
            'amount' => 600000.00,
            'payment_mode' => 'rtgs_neft',
        ]);
        $overpayResponse->assertSessionHasErrors('amount');

        // 6. Settle Balance: ₹530,000
        $pay2Response = $this->actingAs($accountant)->post(route('billing.payments.store', $invoice), [
            'payment_date' => '2026-10-25',
            'amount' => 530000.00,
            'payment_mode' => 'rtgs_neft',
            'reference_number' => 'BMRCL-RTGS-9988',
            'notes' => 'Full settlement of RA Bill 01',
        ]);

        $pay2Response->assertRedirect();
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals('1130000.00', $invoice->fresh()->paid_amount);
        $this->assertEquals(0.00, $invoice->fresh()->balanceDue());

        // 7. Verify Project Financial Overview
        $showProjectResponse = $this->actingAs($accountant)->get(route('projects.show', $project));
        $showProjectResponse->assertOk();

        $financials = $showProjectResponse->viewData('financials');
        $this->assertNotNull($financials);
        $this->assertEquals(5000000.00, $financials['contract_value']);
        $this->assertEquals(1130000.00, $financials['client_bills_raised']);
        $this->assertEquals(1130000.00, $financials['money_received']);
        $this->assertEquals(0.00, $financials['outstanding_client_balance']);
        $this->assertEquals(3870000.00, $financials['unbilled_contract_value']); // 5M - 1.13M
    }
}
