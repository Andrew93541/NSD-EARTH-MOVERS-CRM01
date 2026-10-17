<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Admin Officer',
            'email' => 'admin@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Create sample project
        $project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Metro Underground Tunnel Section 4',
            'code' => 'METRO-UG-04',
            'project_type' => 'civil',
            'status' => 'active',
            'contract_value' => 25000000.00,
            'approved_budget' => 22000000.00,
            'actual_cost' => 12000000.00,
            'progress_percent' => 55,
        ]);

        // Create client & unpaid invoice (Receivables)
        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Metro Rail Corporation',
        ]);

        ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'invoice_number' => 'INV-RA-METRO-01',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'subtotal' => 2000000.00,
            'total_amount' => 2260000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Create supplier & unpaid invoice (Payables)
        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Tata Steel Long Products Ltd',
        ]);

        SupplierInvoice::create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'TATA-INV-9901',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(15),
            'subtotal' => 800000.00,
            'tax_amount' => 144000.00,
            'total_amount' => 944000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
        ]);
    }

    public function test_dashboard_renders_live_database_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Project verification
        $response->assertSee('Metro Underground Tunnel Section 4');
        $response->assertSee('METRO-UG-04');

        // Receivables and Payables derived from database
        $response->assertSee('22.6L'); // Total receivables (2.26M)
        $response->assertSee('9.4L');  // Total payables (944K)
        $response->assertSee('Receivables');
        $response->assertSee('Payables');
    }
}
