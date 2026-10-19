<?php

namespace Tests\Feature\Workflows;

use App\Models\Account;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementAndSupplierPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_procurement_to_supplier_payment_workflow(): void
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
        $adminRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $procurementRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Procurement Officer',
            'slug' => 'procurement_officer',
            'permissions' => ['procurement.*', 'materials.*', 'inventory.*'],
            'is_system' => true,
        ]);

        $accountantRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'procurement.*'],
            'is_system' => true,
        ]);

        $adminUser = User::factory()->create(['name' => 'Admin User']);
        $company->users()->attach($adminUser->id, ['role_id' => $adminRole->id, 'status' => 'active', 'is_primary' => true]);

        $procUser = User::factory()->create(['name' => 'Procurement Head']);
        $company->users()->attach($procUser->id, ['role_id' => $procurementRole->id, 'status' => 'active', 'is_primary' => false]);

        $accountantUser = User::factory()->create(['name' => 'Finance Controller']);
        $company->users()->attach($accountantUser->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => false]);

        // General Ledger Accounts
        $bankAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_active' => true,
        ]);

        $apAccount = Account::create([
            'company_id' => $company->id,
            'code' => '2020',
            'name' => 'Accounts Payable (Suppliers)',
            'account_type' => 'liability',
            'sub_type' => 'current_liability',
            'is_active' => true,
        ]);

        $materialExpenseAccount = Account::create([
            'company_id' => $company->id,
            'code' => '5040',
            'name' => 'Material Procurement & Consumables',
            'account_type' => 'expense',
            'sub_type' => 'direct_cost',
            'is_active' => true,
        ]);

        // Master Data: Supplier, Project, Site, Warehouse, Material
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'UltraTech Cement Corp',
            'contact_person' => 'Suresh Pillai',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'code' => 'PRJ-2026-001',
            'name' => 'Metro Underground Tunneling',
            'status' => 'on_track',
            'approved_budget' => 5000000.00,
        ]);

        $site = Site::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'North Portal Site',
            'code' => 'STE-001',
        ]);

        $warehouse = Warehouse::create([
            'company_id' => $company->id,
            'name' => 'Central Yard Peenya',
            'type' => 'central',
            'is_active' => true,
        ]);

        $material = Material::create([
            'company_id' => $company->id,
            'code' => 'MAT-CEM-01',
            'name' => 'OPC 53 Grade Cement',
            'unit_of_measure' => 'bags',
            'standard_cost' => 380.00,
            'is_active' => true,
        ]);

        // 2. Create Purchase Request, Submit, and Approve
        $pr = PurchaseRequest::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'warehouse_id' => $warehouse->id,
            'pr_number' => 'PR-2026-0001',
            'request_date' => Carbon::now()->toDateString(),
            'requested_by' => $procUser->id,
            'priority' => 'high',
            'required_date' => Carbon::now()->addDays(5)->toDateString(),
            'status' => 'approved',
            'current_approval_level' => 1,
            'required_approval_levels' => 1,
            'estimated_total' => 38000.00,
        ]);

        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $material->id,
            'quantity_requested' => 100,
            'quantity_ordered' => 0,
            'estimated_rate' => 380.00,
            'specifications' => 'Grade 53 ISI marked',
        ]);

        // 3. Convert PR to PO via Procurement Officer
        $poConvertResponse = $this->actingAs($procUser)->post(route('purchase-requests.convert.store', $pr), [
            'supplier_id' => $supplier->id,
            'po_date' => Carbon::now()->toDateString(),
            'items' => [
                [
                    'pr_item_id' => $prItem->id,
                    'quantity_to_order' => 100,
                    'unit_price' => 380.00,
                    'tax_percent' => 18.00,
                ],
            ],
        ]);

        $poConvertResponse->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', [
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'subtotal' => 38000.00,
            'total_amount' => 44840.00, // 38000 + 18% GST (6840)
        ]);

        $po = PurchaseOrder::where('supplier_id', $supplier->id)->firstOrFail();
        $this->assertEquals('po_created', $pr->fresh()->status); // Fully converted

        // 4. Goods Receipt Note (GRN) - Storekeeper receives all 100 bags
        $poItem = $po->items->first();
        $grnResponse = $this->actingAs($procUser)->post(route('procurement.receive.store', $po), [
            'warehouse_id' => $warehouse->id,
            'receipt_date' => Carbon::now()->toDateString(),
            'delivery_challan_number' => 'DC-9921',
            'vehicle_number' => 'KA-04-E-1234',
            'items' => [
                [
                    'item_id' => $poItem->id,
                    'quantity_received' => 100,
                    'quantity_accepted' => 100,
                    'quantity_rejected' => 0,
                ],
            ],
        ]);

        $grnResponse->assertRedirect();
        $this->assertEquals('completed', $po->fresh()->status);
        $this->assertEquals(100, (float) $poItem->fresh()->quantity_received);

        // Verify Stock Ledger Entry credited warehouse
        $stockEntry = StockLedgerEntry::where('material_id', $material->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $this->assertNotNull($stockEntry);
        $this->assertEquals('in', $stockEntry->direction);
        $this->assertEquals(100.00, (float) $stockEntry->quantity);

        // 5. Commercial Supplier Invoice Submission
        $invResponse = $this->actingAs($accountantUser)->post(route('procurement.invoice.store', $po), [
            'invoice_number' => 'INV-UTC-2026-88',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 38000.00,
            'tax_amount' => 6840.00,
        ]);

        $invResponse->assertRedirect();
        $invoice = SupplierInvoice::where('invoice_number', 'INV-UTC-2026-88')->firstOrFail();
        $this->assertEquals('pending_approval', $invoice->status);
        $this->assertEquals('44840.00', $invoice->total_amount);
        $this->assertEquals('0.00', $invoice->paid_amount);

        // 6. Premature Payment Attempt MUST Fail
        $prematureResponse = $this->actingAs($accountantUser)->post(route('procurement.invoice.payment', $invoice), [
            'amount' => 20000.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_mode' => 'bank_transfer',
        ]);
        $prematureResponse->assertSessionHasErrors('status');

        // 7. Approve Supplier Invoice and verify GL Accrual Journal
        $approveInvResponse = $this->actingAs($accountantUser)->post(route('procurement.invoice.approve', $invoice));
        $approveInvResponse->assertRedirect();
        $this->assertEquals('approved', $invoice->fresh()->status);

        $accrualJournal = JournalEntry::where('company_id', $company->id)
            ->where('narration', 'like', '%Supplier invoice accrual%')
            ->first();
        $this->assertNotNull($accrualJournal);
        $this->assertEquals('44840.00', $accrualJournal->total_amount);
        $this->assertTrue($accrualJournal->isBalanced());

        // Check Dr 5040 (Expense) and Cr 2020 (Payable)
        $drLine = $accrualJournal->lines()->where('account_id', $materialExpenseAccount->id)->first();
        $crLine = $accrualJournal->lines()->where('account_id', $apAccount->id)->first();
        $this->assertEquals('44840.00', $drLine->debit);
        $this->assertEquals('44840.00', $crLine->credit);

        // 8. Partial Payment (₹20,000)
        $payment1Response = $this->actingAs($accountantUser)->post(route('procurement.invoice.payment', $invoice), [
            'amount' => 20000.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'UTR1234567890',
            'notes' => 'Part payment batch 1',
        ]);

        $payment1Response->assertRedirect();
        $freshInv = $invoice->fresh();
        $this->assertEquals('partially_paid', $freshInv->status);
        $this->assertEquals('20000.00', $freshInv->paid_amount);
        $this->assertEquals(24840.00, $freshInv->balanceDue());

        // Verify GL Payment Journal: Dr 2020 (AP), Cr 1010 (Bank)
        $paymentJournal1 = JournalEntry::where('company_id', $company->id)
            ->where('narration', 'like', '%Supplier payment%')
            ->first();
        $this->assertNotNull($paymentJournal1);
        $this->assertEquals('20000.00', $paymentJournal1->total_amount);
        $this->assertTrue($paymentJournal1->isBalanced());

        $drAp = $paymentJournal1->lines()->where('account_id', $apAccount->id)->first();
        $crBank = $paymentJournal1->lines()->where('account_id', $bankAccount->id)->first();
        $this->assertEquals('20000.00', $drAp->debit);
        $this->assertEquals('20000.00', $crBank->credit);

        // 9. Overpayment Rejection (attempting to pay ₹30,000 when balance is ₹24,840)
        $overpayResponse = $this->actingAs($accountantUser)->post(route('procurement.invoice.payment', $invoice), [
            'amount' => 30000.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_mode' => 'bank_transfer',
        ]);
        $overpayResponse->assertSessionHasErrors('amount');

        // 10. Final Payment (₹24,840) to settle invoice in full
        $payment2Response = $this->actingAs($accountantUser)->post(route('procurement.invoice.payment', $invoice), [
            'amount' => 24840.00,
            'payment_date' => Carbon::now()->toDateString(),
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'UTR9876543210',
            'notes' => 'Final settlement',
        ]);

        $payment2Response->assertRedirect();
        $finalInv = $invoice->fresh();
        $this->assertEquals('paid', $finalInv->status);
        $this->assertEquals('44840.00', $finalInv->paid_amount);
        $this->assertEquals(0.00, $finalInv->balanceDue());

        // Verify Payment records exist in payments table
        $this->assertEquals(2, Payment::where('payable_type', 'supplier_invoices')->where('payable_id', $invoice->id)->count());
    }
}
