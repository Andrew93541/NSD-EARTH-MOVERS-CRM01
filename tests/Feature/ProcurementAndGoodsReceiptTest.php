<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProcurementAndGoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchaseOfficer;
    protected Company $company;
    protected Project $project;
    protected Supplier $supplier;
    protected Material $aggregateMaterial;
    protected Warehouse $siteStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Procurement Officer',
            'slug' => 'procurement-officer',
            'permissions' => ['*'],
        ]);

        $this->purchaseOfficer = User::create([
            'name' => 'Vikram Joshi',
            'email' => 'purchase@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->purchaseOfficer->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Bengaluru Metro Pier 18',
            'code' => 'BMRCL-P18',
            'status' => 'active',
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Aggregates & Sands',
            'slug' => 'aggregates-sands',
        ]);

        $this->aggregateMaterial = Material::create([
            'company_id' => $this->company->id,
            'material_category_id' => $category->id,
            'code' => 'MAT-AGG-20MM',
            'name' => '20mm Crushed Blue Metal Aggregate',
            'unit_of_measure' => 'tons',
            'standard_cost' => 1250.00,
            'reorder_level' => 50.00,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Deccan Quarry & Crusher Works',
            'contact_person' => 'Gopal Rao',
            'phone' => '+91 98450 12345',
            'gstin' => '29AAACD9981K1ZP',
            'status' => 'active',
        ]);

        $this->siteStore = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Pier 18 On-Site Batching Yard',
            'type' => 'site_store',
            'is_active' => true,
        ]);
    }

    public function test_can_generate_purchase_order_with_line_items(): void
    {
        $postData = [
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_date' => Carbon::now()->toDateString(),
            'delivery_due_date' => Carbon::now()->addDays(7)->toDateString(),
            'material_id' => $this->aggregateMaterial->id,
            'quantity' => 100.00,
            'unit_price' => 1200.00,
            'terms_and_conditions' => 'Standard 30 days payment upon receipt inspection',
        ];

        $response = $this->actingAs($this->purchaseOfficer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.store'), $postData);

        $order = PurchaseOrder::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('procurement.show', $order));

        $this->assertEquals('approved', $order->status);
        $this->assertEquals(120000.00, (float) $order->subtotal);
        $this->assertEquals(21600.00, (float) $order->tax_amount); // 18% GST
        $this->assertEquals(141600.00, (float) $order->total_amount);

        $item = $order->items->first();
        $this->assertEquals(100.00, (float) $item->quantity_ordered);
        $this->assertEquals(0.00, (float) $item->quantity_received);
    }

    public function test_can_view_goods_receipt_inspection_form(): void
    {
        $order = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-TEST1',
            'po_date' => Carbon::now()->toDateString(),
            'subtotal' => 120000.00,
            'tax_amount' => 21600.00,
            'total_amount' => 141600.00,
            'status' => 'approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'material_id' => $this->aggregateMaterial->id,
            'quantity_ordered' => 100.00,
            'unit_price' => 1200.00,
            'tax_percent' => 18.00,
            'total_price' => 141600.00,
            'quantity_received' => 0.00,
        ]);

        $response = $this->actingAs($this->purchaseOfficer)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('procurement.receive', $order));

        $response->assertStatus(200);
        $response->assertSee('Record Goods Receipt (GRN)');
        $response->assertSee('PO-2026-TEST1');
        $response->assertSee('20mm Crushed Blue Metal Aggregate');
        $response->assertSee('Pier 18 On-Site Batching Yard');
    }

    public function test_recording_grn_updates_po_quantity_received_and_credits_stock_ledger(): void
    {
        $order = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-TEST2',
            'po_date' => Carbon::now()->toDateString(),
            'subtotal' => 120000.00,
            'tax_amount' => 21600.00,
            'total_amount' => 141600.00,
            'status' => 'approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'material_id' => $this->aggregateMaterial->id,
            'quantity_ordered' => 100.00,
            'unit_price' => 1200.00,
            'tax_percent' => 18.00,
            'total_price' => 141600.00,
            'quantity_received' => 0.00,
        ]);

        $postData = [
            'warehouse_id' => $this->siteStore->id,
            'receipt_date' => Carbon::now()->toDateString(),
            'delivery_challan_number' => 'DC-DECCAN-4819',
            'vehicle_number' => 'KA-04-MK-9912',
            'remarks' => 'Weighbridge slip verified, quality cleared',
            'items' => [
                [
                    'item_id' => $poItem->id,
                    'quantity_received' => 60.00,
                    'quantity_accepted' => 55.00,
                    'quantity_rejected' => 5.00,
                    'rejection_reason' => 'Excess fines / oversized dust particles',
                ],
            ],
        ];

        $response = $this->actingAs($this->purchaseOfficer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.receive.store', $order), $postData);

        $response->assertRedirect(route('procurement.show', $order));

        $order->refresh();
        $this->assertEquals('partially_received', $order->status);

        $poItem->refresh();
        // Accepted 55 tons must be added to received quantity
        $this->assertEquals(55.00, (float) $poItem->quantity_received);

        // Verify GoodsReceipt created
        $grn = GoodsReceipt::where('purchase_order_id', $order->id)->first();
        $this->assertNotNull($grn);
        $this->assertEquals('DC-DECCAN-4819', $grn->delivery_challan_number);
        $this->assertEquals('KA-04-MK-9912', $grn->vehicle_number);

        // Verify StockLedgerEntry credited exactly 55 tons (not the 5 tons rejected)
        $ledgerEntry = StockLedgerEntry::where('reference_type', 'goods_receipts')
            ->where('reference_id', $grn->id)
            ->first();
        $this->assertNotNull($ledgerEntry);
        $this->assertEquals('in', $ledgerEntry->direction);
        $this->assertEquals(55.00, (float) $ledgerEntry->quantity);
        $this->assertEquals(1200.00, (float) $ledgerEntry->unit_rate);
        $this->assertEquals(66000.00, (float) $ledgerEntry->total_amount);
        $this->assertEquals($this->siteStore->id, $ledgerEntry->warehouse_id);
    }

    public function test_full_goods_receipt_transitions_po_status_to_completed(): void
    {
        $order = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-TEST3',
            'po_date' => Carbon::now()->toDateString(),
            'subtotal' => 24000.00,
            'tax_amount' => 4320.00,
            'total_amount' => 28320.00,
            'status' => 'approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'material_id' => $this->aggregateMaterial->id,
            'quantity_ordered' => 20.00,
            'unit_price' => 1200.00,
            'tax_percent' => 18.00,
            'total_price' => 28320.00,
            'quantity_received' => 0.00,
        ]);

        $postData = [
            'warehouse_id' => $this->siteStore->id,
            'receipt_date' => Carbon::now()->toDateString(),
            'delivery_challan_number' => 'DC-FINAL-100',
            'items' => [
                [
                    'item_id' => $poItem->id,
                    'quantity_received' => 20.00,
                    'quantity_accepted' => 20.00,
                    'quantity_rejected' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchaseOfficer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.receive.store', $order), $postData);

        $response->assertRedirect(route('procurement.show', $order));

        $order->refresh();
        $this->assertEquals('completed', $order->status);
    }

    public function test_cannot_record_grn_exceeding_ordered_balance(): void
    {
        $order = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-TEST4',
            'po_date' => Carbon::now()->toDateString(),
            'subtotal' => 12000.00,
            'tax_amount' => 2160.00,
            'total_amount' => 14160.00,
            'status' => 'approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'material_id' => $this->aggregateMaterial->id,
            'quantity_ordered' => 10.00,
            'unit_price' => 1200.00,
            'tax_percent' => 18.00,
            'total_price' => 14160.00,
            'quantity_received' => 0.00,
        ]);

        // Attempt to receive 50 tons when only 10 were ordered
        $postData = [
            'warehouse_id' => $this->siteStore->id,
            'receipt_date' => Carbon::now()->toDateString(),
            'delivery_challan_number' => 'DC-EXCESS-01',
            'items' => [
                [
                    'item_id' => $poItem->id,
                    'quantity_received' => 50.00,
                    'quantity_accepted' => 50.00,
                    'quantity_rejected' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchaseOfficer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.receive.store', $order), $postData);

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, GoodsReceipt::count());
    }

    public function test_can_record_supplier_commercial_invoice_against_po(): void
    {
        $order = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'po_number' => 'PO-2026-TEST5',
            'po_date' => Carbon::now()->toDateString(),
            'subtotal' => 50000.00,
            'tax_amount' => 9000.00,
            'total_amount' => 59000.00,
            'status' => 'approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        $postData = [
            'invoice_number' => 'INV-DECCAN-8891',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 50000.00,
            'tax_amount' => 9000.00,
        ];

        $response = $this->actingAs($this->purchaseOfficer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.invoice.store', $order), $postData);

        $response->assertRedirect(route('procurement.show', $order));

        $invoice = SupplierInvoice::where('invoice_number', 'INV-DECCAN-8891')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($order->id, $invoice->purchase_order_id);
        $this->assertEquals(59000.00, (float) $invoice->total_amount);
        $this->assertEquals(59000.00, $invoice->balanceDue());
    }
}
