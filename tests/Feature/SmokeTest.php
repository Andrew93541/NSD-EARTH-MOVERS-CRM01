<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Loan;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Project $project;
    protected Site $site;
    protected Employee $employee;
    protected ClientInvoice $invoice;
    protected PurchaseOrder $order;
    protected Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Adani Infrastructure',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'Solar Park Grading',
            'code' => 'SP-01',
            'project_type' => 'earthmoving',
            'contract_value' => 5000000.00,
            'approved_budget' => 4200000.00,
            'status' => 'on_track',
            'progress_percent' => 50,
            'manager_id' => $this->user->id,
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'North Solar Array',
            'code' => 'SITE-NS-01',
            'supervisor_id' => $this->user->id,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'EMP-001',
            'first_name' => 'Ravi',
            'last_name' => 'Verma',
            'employment_type' => 'permanent',
            'monthly_salary' => 45000.00,
            'status' => 'active',
        ]);

        $matCat = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Civil',
            'code' => 'CIV',
        ]);

        Material::create([
            'company_id' => $this->company->id,
            'category_id' => $matCat->id,
            'code' => 'MAT-CEM-01',
            'name' => 'OPC 53 Cement',
            'unit_of_measure' => 'Bags',
            'standard_cost' => 380.00,
        ]);

        Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Main Yard',
            'code' => 'WH-01',
            'type' => 'central',
        ]);

        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'JSW Steel Ltd',
        ]);

        $this->order = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-001',
            'po_date' => Carbon::today(),
            'subtotal' => 100000.00,
            'tax_amount' => 18000.00,
            'total_amount' => 118000.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        SupplierInvoice::create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $this->order->id,
            'invoice_number' => 'JSW-INV-001',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'subtotal' => 100000.00,
            'tax_amount' => 18000.00,
            'total_amount' => 118000.00,
            'status' => 'pending_approval',
        ]);

        $this->invoice = ClientInvoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $this->project->id,
            'invoice_number' => 'INV-RA-01',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'subtotal' => 500000.00,
            'total_amount' => 565000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $expCat = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Fuel & Oil',
            'code' => 'FUEL',
        ]);

        Expense::create([
            'company_id' => $this->company->id,
            'category_id' => $expCat->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'expense_number' => 'EXP-2026-001',
            'title' => 'Excavator Diesel Supply',
            'expense_date' => Carbon::today(),
            'amount' => 25000.00,
            'submitted_by' => $this->user->id,
            'status' => 'approved',
        ]);

        $this->loan = Loan::create([
            'company_id' => $this->company->id,
            'loan_account_number' => 'LN-EQ-9921',
            'lender_name' => 'HDFC Bank Equipment Finance',
            'loan_type' => 'equipment_finance',
            'principal_amount' => 4500000.00,
            'annual_interest_rate' => 9.50,
            'tenure_months' => 36,
            'start_date' => Carbon::today()->subMonths(3),
            'monthly_emi' => 144186.00,
            'outstanding_principal' => 4150000.00,
            'status' => 'active',
        ]);

        Account::create([
            'company_id' => $this->company->id,
            'code' => '1001',
            'name' => 'Main Current Bank',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);
    }

    public function test_all_primary_pages_render_without_error(): void
    {
        $urls = [
            '/dashboard',
            '/projects',
            '/projects/create',
            "/projects/{$this->project->id}",
            "/projects/{$this->project->id}/edit",
            '/sites',
            "/sites/{$this->site->id}",
            "/sites/{$this->site->id}/logs/create",
            '/attendance',
            '/attendance/muster-roll',
            '/employees',
            '/employees/create',
            "/employees/{$this->employee->id}",
            '/inventory',
            '/inventory/ledger',
            '/inventory/transfer',
            '/procurement',
            '/procurement/create-po',
            "/procurement/orders/{$this->order->id}",
            '/procurement/supplier-invoices',
            '/billing',
            '/billing/create',
            "/billing/{$this->invoice->id}",
            '/expenses',
            '/expenses/create',
            '/loans',
            '/loans/create',
            "/loans/{$this->loan->id}",
            '/accounting',
            '/accounting/journals',
            '/accounting/journals/create',
            '/accounting/trial-balance',
            '/search?q=Solar',
            '/workspace/select',
            '/workspace/create',
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($this->user)
                ->withSession(['current_company_id' => $this->company->id])
                ->get($url);

            $this->assertEquals(
                200,
                $response->getStatusCode(),
                "Failed asserting that [{$url}] returned HTTP 200. Got: {$response->getStatusCode()}"
            );
        }
    }
}
