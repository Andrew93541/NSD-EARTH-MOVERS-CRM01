<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;
    protected User $siteSupervisorUser;
    protected Company $companyA;
    protected Company $companyB;
    protected Project $project;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $ownerRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->ownerUser = User::create([
            'name' => 'Narayan Shinde',
            'email' => 'owner@nsdconstruction.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->companyA->users()->attach($this->ownerUser->id, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Restricted role (site supervisor without procurement/supplier manage permissions)
        $supervisorRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'daily_logs.*', 'attendance.record'],
        ]);

        $this->siteSupervisorUser = User::create([
            'name' => 'Ramesh Patil',
            'email' => 'supervisor@nsdconstruction.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->companyA->users()->attach($this->siteSupervisorUser->id, [
            'role_id' => $supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Competitor Infra Pvt Ltd',
            'slug' => 'competitor-infra',
        ]);

        $this->project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Ring Road Interchange',
            'code' => 'RRI-01',
            'contract_value' => 5000000.00,
            'approved_budget' => 4500000.00,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->companyA->id,
            'name' => 'Central Depot Pune',
            'location' => 'Hadapsar Yard',
            'status' => 'active',
        ]);
    }

    public function test_supplier_list_displays_material_suppliers_and_dual_role_but_excludes_pure_subcontractors(): void
    {
        // 1. Pure Material Supplier (Should appear in /suppliers)
        $materialSupplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Ambuja Cements Ltd',
            'vendor_type' => 'supplier',
            'status' => 'active',
        ]);

        // 2. Dual Role Supplier & Contractor (Should appear in /suppliers)
        $dualVendor = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'BuildTech ReadyMix & Paving',
            'vendor_type' => 'both',
            'status' => 'active',
        ]);

        // 3. Pure Subcontractor (Should NOT appear in /suppliers)
        $subcontractor = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Om Earthmovers JCB Excavation',
            'vendor_type' => 'subcontractor',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('suppliers.index'));

        $response->assertStatus(200);
        $response->assertSee('Ambuja Cements Ltd');
        $response->assertSee('BuildTech ReadyMix & Paving');
        $response->assertDontSee('Om Earthmovers JCB Excavation');
    }

    public function test_subcontractor_register_excludes_pure_material_suppliers(): void
    {
        $materialSupplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Tata Steel Long Products',
            'vendor_type' => 'supplier',
            'status' => 'active',
        ]);

        $subcontractor = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Sai Piling and Deep Foundations',
            'vendor_type' => 'subcontractor',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('subcontractors.index'));

        $response->assertStatus(200);
        $response->assertSee('Sai Piling and Deep Foundations');
        $response->assertDontSee('Tata Steel Long Products');
    }

    public function test_can_create_material_supplier_with_valid_data(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('suppliers.store'), [
                'name' => 'JSW Steel Processing Ltd',
                'vendor_type' => 'supplier',
                'contact_person' => 'Dinesh Sharma',
                'phone' => '+91 22 2400 1122',
                'email' => 'sales@jswsteel.in',
                'gstin' => '27AAACJ1234A1Z1',
                'pan' => 'AAACJ1234A',
                'address' => 'Bandra Kurla Complex, Mumbai',
                'payment_terms' => '30_days',
                'bank_name' => 'ICICI Bank',
                'bank_account_number' => '001105001234',
                'ifsc_code' => 'ICIC0000011',
                'status' => 'active',
            ]);

        $supplier = Supplier::where('gstin', '27AAACJ1234A1Z1')->first();
        $this->assertNotNull($supplier);
        $response->assertRedirect(route('suppliers.show', $supplier));

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $this->companyA->id,
            'name' => 'JSW Steel Processing Ltd',
            'vendor_type' => 'supplier',
            'payment_terms' => '30_days',
            'status' => 'active',
        ]);
    }

    public function test_creating_supplier_validates_required_fields(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('suppliers.store'), [
                'name' => '',
                'vendor_type' => 'invalid_type',
                'payment_terms' => 'invalid_terms',
                'status' => '',
            ]);

        $response->assertSessionHasErrors(['name', 'vendor_type', 'payment_terms', 'status']);
    }

    public function test_can_view_supplier_details_with_pos_grns_and_invoices(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Birla Shakti Aggregates',
            'vendor_type' => 'supplier',
            'status' => 'active',
            'payment_terms' => '30_days',
        ]);

        $po = PurchaseOrder::create([
            'company_id' => $this->companyA->id,
            'supplier_id' => $supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-0099',
            'po_date' => Carbon::today()->format('Y-m-d'),
            'total_amount' => 450000.00,
            'status' => 'approved',
            'created_by' => $this->ownerUser->id,
        ]);

        $grn = GoodsReceipt::create([
            'company_id' => $this->companyA->id,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-2026-0099',
            'receipt_date' => Carbon::today()->format('Y-m-d'),
            'delivery_challan_number' => 'DC-8841',
            'vehicle_number' => 'MH-12-AB-1234',
            'received_by' => $this->ownerUser->id,
        ]);

        $invoice = SupplierInvoice::create([
            'company_id' => $this->companyA->id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'invoice_number' => 'SUP-INV-8812',
            'invoice_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
            'subtotal' => 381355.93,
            'tax_amount' => 68644.07,
            'total_amount' => 450000.00,
            'paid_amount' => 200000.00,
            'status' => 'partially_paid',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('suppliers.show', $supplier));

        $response->assertStatus(200);
        $response->assertSee('Birla Shakti Aggregates');
        $response->assertSee('PO-2026-0099');
        $response->assertSee('GRN-2026-0099');
        $response->assertSee('SUP-INV-8812');
        $response->assertSee('450,000.00'); // PO total
        $response->assertSee('200,000.00'); // Paid to date
        $response->assertSee('250,000.00'); // Outstanding payable
    }

    public function test_can_update_supplier_details(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'HPCL Fuel Distributors',
            'vendor_type' => 'supplier',
            'payment_terms' => 'immediate',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('suppliers.update', $supplier), [
                'name' => 'HPCL Direct Diesel Terminal',
                'vendor_type' => 'supplier',
                'payment_terms' => '15_days',
                'contact_person' => 'Sunil Gaikwad',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('suppliers.show', $supplier));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'HPCL Direct Diesel Terminal',
            'payment_terms' => '15_days',
            'contact_person' => 'Sunil Gaikwad',
        ]);
    }

    public function test_tenant_isolation_cannot_view_or_edit_supplier_from_another_company(): void
    {
        $supplierCompanyB = Supplier::create([
            'company_id' => $this->companyB->id,
            'name' => 'Competitor Sand Supplier',
            'vendor_type' => 'supplier',
            'status' => 'active',
        ]);

        // Attempt viewing
        $viewResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('suppliers.show', $supplierCompanyB));
        $viewResponse->assertStatus(404);

        // Attempt editing
        $editResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('suppliers.edit', $supplierCompanyB));
        $editResponse->assertStatus(404);

        // Attempt updating
        $updateResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('suppliers.update', $supplierCompanyB), [
                'name' => 'Hijacked Supplier',
                'vendor_type' => 'supplier',
                'payment_terms' => '30_days',
                'status' => 'active',
            ]);
        $updateResponse->assertStatus(404);
    }

    public function test_unauthorized_user_cannot_access_suppliers_management_via_direct_http(): void
    {
        $responseList = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('suppliers.index'));
        $responseList->assertStatus(403);

        $responseCreate = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('suppliers.create'));
        $responseCreate->assertStatus(403);

        $responseStore = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('suppliers.store'), [
                'name' => 'Unauthorized Vendor',
                'vendor_type' => 'supplier',
                'payment_terms' => '30_days',
                'status' => 'active',
            ]);
        $responseStore->assertStatus(403);
    }

    public function test_material_suppliers_navigation_visibility(): void
    {
        // Owner sees Material Suppliers
        $authResponse = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('dashboard'));
        $authResponse->assertStatus(200);
        $authResponse->assertSee('Material Suppliers');

        // Unauthorized supervisor does NOT see Material Suppliers
        $unauthResponse = $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('dashboard'));
        $unauthResponse->assertStatus(200);
        $unauthResponse->assertDontSee('Material Suppliers');
    }
}
