<?php

namespace Tests\Feature\Workflows;

use App\Models\Client;
use App\Models\ClientContract;
use App\Models\Company;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientQuotationProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Role $ownerRole;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'legal_name' => 'NSD Earth Movers & Construction Limited',
            'slug' => 'nsd-earth-movers',
            'code' => 'NSD',
            'status' => 'active',
        ]);

        $this->ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'rajesh.varma@nsdinfra.test',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->owner->id, [
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    /**
     * Workflow 1: Client creation -> Quotation -> Status Lifecycle -> Project & Contract Conversion.
     */
    public function test_complete_client_quotation_project_conversion_workflow(): void
    {
        // 1. Client Creation
        $clientResponse = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('clients.store'), [
                'name' => 'Tata Steel Infrastructure Division',
                'company_name' => 'Tata Steel Ltd',
                'contact_person' => 'Kailash Singhania',
                'email' => 'infra.procurement@tatasteel.test',
                'phone' => '+91 98200 11223',
                'gstin' => '27AAACT2727Q1ZW',
                'city' => 'Nagpur',
                'state' => 'Maharashtra',
                'status' => 'active',
            ]);

        $clientResponse->assertRedirect();
        $client = Client::where('email', 'infra.procurement@tatasteel.test')->first();
        $this->assertNotNull($client);
        $this->assertSame($this->company->id, (int) $client->company_id);

        // 2. Commercial Quotation Creation with Line Items, GST, and Discount
        $quoteResponse = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.store'), [
                'client_id' => $client->id,
                'quotation_number' => 'QT-2026-0099',
                'date' => Carbon::today()->toDateString(),
                'valid_until' => Carbon::today()->addDays(30)->toDateString(),
                'project_title' => 'Bulk Earthmoving & Industrial Site Grading Package',
                'project_description' => 'Heavy excavation, filling, and compaction for industrial park.',
                'tax_type' => 'gst_18',
                'tax_rate' => 18.00,
                'discount_type' => 'fixed',
                'discount_amount' => 50000.00,
                'items' => [
                    [
                        'description' => 'Site clearing, grubbing and topsoil stripping',
                        'quantity' => 25000.00,
                        'unit' => 'sqm',
                        'unit_rate' => 35.00,
                    ],
                    [
                        'description' => 'Bulk earth excavation in all types of soils',
                        'quantity' => 12000.00,
                        'unit' => 'cum',
                        'unit_rate' => 180.00,
                    ],
                ],
            ]);

        $quoteResponse->assertRedirect();
        $quotation = Quotation::where('quotation_number', 'QT-2026-0099')->first();
        $this->assertNotNull($quotation);
        $this->assertSame('draft', $quotation->status);
        $this->assertCount(2, $quotation->items);

        // Subtotal = (25000 * 35) + (12000 * 180) = 875,000 + 2,160,000 = 3,035,000.00
        $this->assertEquals(3035000.00, (float) $quotation->subtotal);
        // Taxable = 3,035,000 - 50,000 = 2,985,000.00
        // GST 18% = 537,300.00
        // Total = 3,522,300.00
        $this->assertEquals(3522300.00, (float) $quotation->total_amount);

        // 3. Status Transition: Draft -> Sent -> Accepted
        $sentResponse = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->patch(route('quotations.status', $quotation), [
                'status' => 'sent',
            ]);
        $sentResponse->assertRedirect();
        $quotation->refresh();
        $this->assertSame('sent', $quotation->status);

        $acceptedResponse = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->patch(route('quotations.status', $quotation), [
                'status' => 'accepted',
            ]);
        $acceptedResponse->assertRedirect();
        $quotation->refresh();
        $this->assertSame('accepted', $quotation->status);

        // 4. Project Conversion
        $convertResponse = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.convert', $quotation), [
                'project_name' => 'Tata Industrial Grading - Package 1',
                'project_type' => 'earthmoving',
                'start_date' => Carbon::today()->toDateString(),
                'target_completion_date' => Carbon::today()->addMonths(6)->toDateString(),
            ]);

        $convertResponse->assertRedirect();
        $quotation->refresh();
        $this->assertNotNull($quotation->converted_project_id);

        $project = Project::find($quotation->converted_project_id);
        $this->assertNotNull($project);
        $this->assertSame('Tata Industrial Grading - Package 1', $project->name);
        $this->assertSame('earthmoving', $project->project_type);
        $this->assertSame($client->id, (int) $project->client_id);
        $this->assertSame($this->company->id, (int) $project->company_id);
        $this->assertEquals(3522300.00, (float) $project->contract_value);
        $this->assertEquals(3035000.00, (float) $project->approved_budget);

        // Verify linked Client Contract was created
        $contract = ClientContract::where('project_id', $project->id)->first();
        $this->assertNotNull($contract);
        $this->assertSame($client->id, (int) $contract->client_id);
        $this->assertEquals(3522300.00, (float) $contract->contract_value);

        // 5. Duplicate conversion attempt must be rejected
        $dupResponse = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('quotations.convert', $quotation), [
                'project_name' => 'Duplicate Attempt',
            ]);
        $dupResponse->assertRedirect();
        $dupResponse->assertSessionHas('error');
    }
}
