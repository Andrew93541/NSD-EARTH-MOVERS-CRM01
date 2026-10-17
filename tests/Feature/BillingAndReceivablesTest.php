<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingAndReceivablesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Client $client;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Billing Engineer',
            'slug' => 'billing-engineer',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Billing Officer',
            'email' => 'billing@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Adani Realty Ltd',
            'email' => 'accounts@adanirealty.com',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Adani Shantigram Earthmoving',
            'code' => 'ADANI-SHANTI-01',
            'project_type' => 'earthmoving',
            'contract_value' => 10000000.00,
            'approved_budget' => 8500000.00,
            'status' => 'active',
        ]);
    }

    public function test_can_generate_progress_ra_bill_with_taxes_and_retention(): void
    {
        // Subtotal = 1,000,000. 9% CGST = 90,000, 9% SGST = 90,000. 5% Retention = 50,000.
        // Total = 1,000,000 + 90,000 + 90,000 - 50,000 = 1,130,000
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/billing', [
                'client_id' => $this->client->id,
                'project_id' => $this->project->id,
                'bill_type' => 'progress_ra',
                'issue_date' => Carbon::today()->format('Y-m-d'),
                'due_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
                'subtotal' => 1000000.00,
                'retention_rate_percent' => 5.0,
                'notes' => 'RA Bill 01 for Site Levelling and Earth Cutting',
            ]);

        $invoice = ClientInvoice::latest()->first();
        $this->assertNotNull($invoice);
        $response->assertRedirect(route('billing.show', $invoice));

        $this->assertEquals(1000000.00, $invoice->subtotal);
        $this->assertEquals(90000.00, $invoice->cgst_amount);
        $this->assertEquals(90000.00, $invoice->sgst_amount);
        $this->assertEquals(50000.00, $invoice->retention_amount);
        $this->assertEquals(1130000.00, $invoice->total_amount);
        $this->assertEquals(0.00, $invoice->paid_amount);
        $this->assertEquals('approved', $invoice->status);
    }

    public function test_supports_partial_payments_and_tracks_outstanding_balance(): void
    {
        $invoice = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-TEST-001',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(15),
            'subtotal' => 500000.00,
            'cgst_amount' => 45000.00,
            'sgst_amount' => 45000.00,
            'retention_amount' => 25000.00,
            'total_amount' => 565000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // First partial payment: 300,000
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/billing/{$invoice->id}/payments", [
                'payment_date' => Carbon::today()->format('Y-m-d'),
                'amount' => 300000.00,
                'payment_mode' => 'rtgs_neft',
                'reference_number' => 'RTGS-ADANI-44910',
                'notes' => 'Part-payment 1 received via RTGS',
            ]);

        $response->assertRedirect(route('billing.show', $invoice));

        $invoice->refresh();
        $this->assertEquals(300000.00, $invoice->paid_amount);
        $this->assertEquals(265000.00, $invoice->balanceDue());
        $this->assertEquals('partially_paid', $invoice->status);

        // Second payment: remaining 265,000 to fully settle
        $res = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/billing/{$invoice->id}/payments", [
                'payment_date' => Carbon::today()->format('Y-m-d'),
                'amount' => 265000.00,
                'payment_mode' => 'rtgs_neft',
                'reference_number' => 'RTGS-ADANI-44999',
            ]);

        $res->assertRedirect(route('billing.show', $invoice));

        $invoice->refresh();
        $this->assertEquals(565000.00, $invoice->paid_amount);
        $this->assertEquals(0.00, $invoice->balanceDue());
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_rejects_payment_exceeding_outstanding_balance(): void
    {
        $invoice = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-TEST-002',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(15),
            'subtotal' => 100000.00,
            'total_amount' => 113000.00,
            'paid_amount' => 100000.00,
            'status' => 'partially_paid',
            'created_by' => $this->user->id,
        ]);

        // Balance due is 13,000. Attempt to pay 20,000.
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/billing/{$invoice->id}/payments", [
                'payment_date' => Carbon::today()->format('Y-m-d'),
                'amount' => 20000.00,
                'payment_mode' => 'cash',
            ]);

        $response->assertSessionHasErrors('amount');
    }
}
