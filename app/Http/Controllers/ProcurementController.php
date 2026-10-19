<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    protected function getCompanyId(): int
    {
        return (int) (session('current_company_id')
            ?? Auth::user()?->currentCompany()?->id
            ?? Company::configuredCompanyId()
            ?? Company::first()?->id
            ?? 1);
    }

    protected function authorizeDirectPO(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $isProcurement = $user && ($user->hasPermission('*') || $user->hasPermission('procurement.*') || in_array($roleSlug, ['owner', 'admin', 'procurement_officer']));
        if (! $isProcurement) {
            abort(403, 'Unauthorized. Direct purchase order creation is restricted to Procurement Officers and Administrators.');
        }
    }

    protected function authorizeGRN(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $canReceive = $user && ($user->hasPermission('*') || $user->hasPermission('procurement.*') || $user->hasPermission('inventory.*') || in_array($roleSlug, ['owner', 'admin', 'storekeeper', 'procurement_officer']));
        if (! $canReceive) {
            abort(403, 'Unauthorized. Goods receipt inspection is restricted to Storekeepers and Procurement Officers.');
        }
    }

    protected function authorizeSupplierInvoice(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $canInvoice = $user && ($user->hasPermission('*') || $user->hasPermission('procurement.*') || $user->hasPermission('finance.*') || in_array($roleSlug, ['owner', 'admin', 'accountant', 'procurement_officer']));
        if (! $canInvoice) {
            abort(403, 'Unauthorized. Only Finance or Procurement Officers can record commercial supplier invoices.');
        }
    }

    public function index(Request $request): View
    {
        $companyId = $this->getCompanyId();

        $orders = PurchaseOrder::where('company_id', $companyId)
            ->with(['supplier', 'project', 'creator'])
            ->latest()
            ->paginate(10);

        $suppliers = Supplier::where('company_id', $companyId)->withCount('purchaseOrders')->get();

        $stats = [
            'total_pos' => PurchaseOrder::where('company_id', $companyId)->count(),
            'pending_pos' => PurchaseOrder::where('company_id', $companyId)->where('status', 'draft')->count(),
            'total_po_value' => PurchaseOrder::where('company_id', $companyId)->sum('total_amount'),
        ];

        return view('procurement.index', compact('orders', 'suppliers', 'stats'));
    }

    public function createPO(): View
    {
        $this->authorizeDirectPO();
        $companyId = $this->getCompanyId();

        $suppliers = Supplier::where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get();
        $projects = Project::where('company_id', $companyId)->whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();
        $materials = Material::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        return view('procurement.create-po', compact('suppliers', 'projects', 'materials'));
    }

    public function storePO(Request $request): RedirectResponse
    {
        $this->authorizeDirectPO();
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'project_id' => 'required|exists:projects,id',
            'po_date' => 'required|date',
            'delivery_due_date' => 'nullable|date|after_or_equal:po_date',
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|between:0,100',
            'terms_and_conditions' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $taxRate = $request->filled('tax_rate') ? (float) $request->input('tax_rate') : 18.00;

        $subtotal = round($validated['quantity'] * $validated['unit_price'], 2);
        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);

        $po = PurchaseOrder::create([
            'company_id' => $companyId,
            'supplier_id' => $validated['supplier_id'],
            'project_id' => $validated['project_id'],
            'po_number' => 'PO-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'po_date' => $validated['po_date'],
            'delivery_due_date' => $validated['delivery_due_date'],
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'status' => 'approved',
            'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
            'created_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'material_id' => $validated['material_id'],
            'quantity_ordered' => $validated['quantity'],
            'unit_price' => $validated['unit_price'],
            'tax_percent' => $taxRate,
            'total_price' => $total,
        ]);

        if ($po->project?->manager_id) {
            AppNotification::create([
                'company_id' => $po->company_id,
                'user_id' => $po->project->manager_id,
                'title' => 'Purchase Order Issued',
                'message' => "Purchase Order {$po->po_number} generated for {$po->supplier->name}.",
                'type' => 'info',
                'action_url' => route('procurement.show', $po),
                'is_read' => false,
            ]);
        }

        return redirect()->route('procurement.show', $po)->with('success', "Purchase Order {$po->po_number} generated and approved.");
    }

    public function show(PurchaseOrder $order): View
    {
        $order->load([
            'supplier',
            'project',
            'items.material',
            'creator',
            'approver',
            'goodsReceipts.warehouse',
            'goodsReceipts.items.material',
            'goodsReceipts.receiver',
            'supplierInvoices',
        ]);

        return view('procurement.show', compact('order'));
    }

    public function createGRN(PurchaseOrder $order): View|RedirectResponse
    {
        $this->authorizeGRN();

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return redirect()->route('procurement.show', $order)
                ->withErrors(['status' => 'This purchase order is already marked as ' . $order->status . ' and cannot accept further goods receipts.']);
        }

        $order->load(['items.material', 'supplier', 'project']);
        $companyId = $order->company_id ?? $this->getCompanyId();
        $warehouses = Warehouse::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        return view('procurement.receive', compact('order', 'warehouses'));
    }

    public function storeGRN(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeGRN();

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return redirect()->route('procurement.show', $order)
                ->withErrors(['status' => 'This purchase order is already marked as ' . $order->status . '.']);
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'receipt_date' => 'required|date',
            'delivery_challan_number' => 'required|string|max:100',
            'vehicle_number' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.quantity_accepted' => 'required|numeric|min:0',
            'items.*.quantity_rejected' => 'nullable|numeric|min:0',
            'items.*.rejection_reason' => 'nullable|string|max:255',
        ]);

        $order->load('items');

        // Validation: verify quantities
        $totalReceivedInBatch = 0;
        foreach ($validated['items'] as $itemData) {
            $poItem = $order->items->firstWhere('id', $itemData['item_id']);
            if (!$poItem) {
                return back()->withInput()->withErrors(['items' => 'Invalid PO item specified.']);
            }

            $rec = (float) $itemData['quantity_received'];
            $acc = (float) $itemData['quantity_accepted'];
            $rej = (float) ($itemData['quantity_rejected'] ?? 0);

            if (round($acc + $rej, 3) !== round($rec, 3)) {
                return back()->withInput()->withErrors([
                    'items' => "For material {$poItem->material->name}, accepted ({$acc}) + rejected ({$rej}) must equal total received ({$rec}).",
                ]);
            }

            $remainingAllowed = (float) ($poItem->quantity_ordered - $poItem->quantity_received);
            if ($acc > $remainingAllowed + 0.0001) {
                return back()->withInput()->withErrors([
                    'items' => "Accepted quantity ({$acc}) exceeds remaining ordered quantity ({$remainingAllowed}) for {$poItem->material->name}.",
                ]);
            }

            $totalReceivedInBatch += $rec;
        }

        if ($totalReceivedInBatch <= 0) {
            return back()->withInput()->withErrors([
                'items' => 'At least one material item must have a received quantity greater than zero.',
            ]);
        }

        $companyId = $order->company_id ?? $this->getCompanyId();

        DB::transaction(function () use ($validated, $order, $companyId) {
            $grn = GoodsReceipt::create([
                'company_id' => $companyId,
                'purchase_order_id' => $order->id,
                'warehouse_id' => $validated['warehouse_id'],
                'grn_number' => 'GRN-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'receipt_date' => $validated['receipt_date'],
                'delivery_challan_number' => $validated['delivery_challan_number'],
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'received_by' => Auth::id(),
                'remarks' => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $poItem = $order->items->firstWhere('id', $itemData['item_id']);
                $rec = (float) $itemData['quantity_received'];
                $acc = (float) $itemData['quantity_accepted'];
                $rej = (float) ($itemData['quantity_rejected'] ?? 0);

                if ($rec <= 0) {
                    continue;
                }

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $grn->id,
                    'material_id' => $poItem->material_id,
                    'quantity_received' => $rec,
                    'quantity_accepted' => $acc,
                    'quantity_rejected' => $rej,
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                ]);

                // Update PO item quantity_received
                $poItem->increment('quantity_received', $acc);

                // Update stock ledger if accepted > 0
                if ($acc > 0) {
                    $currentWarehouseStock = StockLedgerEntry::where('material_id', $poItem->material_id)
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->where('direction', 'in')->sum('quantity') -
                        StockLedgerEntry::where('material_id', $poItem->material_id)
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->where('direction', 'out')->sum('quantity');

                    StockLedgerEntry::create([
                        'company_id' => $companyId,
                        'material_id' => $poItem->material_id,
                        'warehouse_id' => $validated['warehouse_id'],
                        'entry_date' => $validated['receipt_date'],
                        'transaction_type' => 'goods_receipt',
                        'reference_type' => 'goods_receipts',
                        'reference_id' => $grn->id,
                        'quantity' => $acc,
                        'direction' => 'in',
                        'unit_rate' => $poItem->unit_price,
                        'total_amount' => round($acc * (float) $poItem->unit_price, 2),
                        'balance_quantity' => $currentWarehouseStock + $acc,
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            // Determine if PO is fully received
            $order->refresh();
            $allCompleted = true;
            foreach ($order->items as $item) {
                if ((float) $item->quantity_received < (float) $item->quantity_ordered) {
                    $allCompleted = false;
                    break;
                }
            }

            $order->update([
                'status' => $allCompleted ? 'completed' : 'partially_received',
            ]);

            if ($order->created_by || $order->project?->manager_id) {
                AppNotification::create([
                    'company_id' => $companyId,
                    'user_id' => $order->created_by ?: $order->project?->manager_id,
                    'title' => 'Goods Receipt Recorded',
                    'message' => "Goods Receipt {$grn->grn_number} recorded for PO {$order->po_number}.",
                    'type' => 'success',
                    'action_url' => route('procurement.show', $order),
                    'is_read' => false,
                ]);
            }
        });

        return redirect()->route('procurement.show', $order)
            ->with('success', "Goods Receipt Note recorded successfully. Warehouse inventory has been credited.");
    }

    public function storeSupplierInvoice(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeSupplierInvoice();

        $validated = $request->validate([
            'invoice_number' => 'required|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
        ]);

        $tax = (float) ($validated['tax_amount'] ?? 0);
        $total = (float) $validated['subtotal'] + $tax;
        $companyId = $order->company_id ?? $this->getCompanyId();

        SupplierInvoice::create([
            'company_id' => $companyId,
            'supplier_id' => $order->supplier_id,
            'purchase_order_id' => $order->id,
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'subtotal' => $validated['subtotal'],
            'tax_amount' => $tax,
            'total_amount' => $total,
            'paid_amount' => 0.00,
            'status' => 'pending_approval',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return redirect()->route('procurement.show', $order)
            ->with('success', "Supplier Invoice {$validated['invoice_number']} recorded and submitted for approval.");
    }

    public function approveSupplierInvoice(Request $request, SupplierInvoice $invoice): JsonResponse|RedirectResponse
    {
        $this->authorizeSupplierInvoice();

        if ($invoice->status !== 'pending_approval') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only pending supplier invoices can be approved.'], 422);
            }
            return back()->withErrors(['status' => 'Only pending supplier invoices can be approved.']);
        }

        DB::transaction(function () use ($invoice) {
            $lockedInvoice = SupplierInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            $lockedInvoice->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => Carbon::now(),
            ]);

            // Post GL Accrual Journal Entry: Dr 5040 (Material Procurement), Cr 2020 (Accounts Payable)
            $companyId = $lockedInvoice->company_id ?? $this->getCompanyId();
            $procurementAccount = Account::where('company_id', $companyId)->where('code', '5040')->first();
            $apAccount = Account::where('company_id', $companyId)->where('code', '2020')->first();

            if ($procurementAccount && $apAccount && $lockedInvoice->total_amount > 0) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => $lockedInvoice->invoice_date->toDateString(),
                    'narration' => "Supplier invoice accrual for {$lockedInvoice->invoice_number} ({$lockedInvoice->supplier->name})",
                    'total_amount' => $lockedInvoice->total_amount,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit Material Procurement Expense
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $procurementAccount->id,
                    'debit' => $lockedInvoice->total_amount,
                    'credit' => 0.00,
                    'description' => "Material procurement cost for invoice {$lockedInvoice->invoice_number}",
                ]);

                // Credit Accounts Payable
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $apAccount->id,
                    'debit' => 0.00,
                    'credit' => $lockedInvoice->total_amount,
                    'description' => "Payable to {$lockedInvoice->supplier->name} for invoice {$lockedInvoice->invoice_number}",
                ]);

                JournalEntry::lockAfterCreation($journalEntry->id);

                AuditLog::create([
                    'company_id' => $companyId,
                    'user_id' => Auth::id(),
                    'action' => 'supplier_invoice_accrual_posted',
                    'auditable_type' => SupplierInvoice::class,
                    'auditable_id' => $lockedInvoice->id,
                    'new_values' => [
                        'invoice_number' => $lockedInvoice->invoice_number,
                        'journal_entry_id' => $journalEntry->id,
                        'amount' => $lockedInvoice->total_amount,
                    ],
                ]);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier Invoice {$invoice->invoice_number} approved successfully.",
                'status' => 'approved',
            ]);
        }

        return back()->with('success', "Supplier Invoice {$invoice->invoice_number} approved successfully.");
    }

    public function recordPayment(Request $request, SupplierInvoice $invoice): JsonResponse|RedirectResponse
    {
        $this->authorizeSupplierInvoice();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_mode' => 'required|string|in:bank_transfer,neft_rtgs,cheque,upi,cash',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($invoice->status === 'pending_approval') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => "Supplier invoice {$invoice->invoice_number} must be approved before recording payment."], 422);
            }
            return back()->withErrors(['status' => "Supplier invoice {$invoice->invoice_number} must be approved before recording payment."]);
        }

        if ($invoice->status === 'paid') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => "Supplier invoice {$invoice->invoice_number} is already fully paid."], 422);
            }
            return back()->withErrors(['status' => "Supplier invoice {$invoice->invoice_number} is already fully paid."]);
        }

        $paymentAmount = round((float) $validated['amount'], 2);
        $remainingBalance = round((float) ($invoice->total_amount - $invoice->paid_amount), 2);

        if ($paymentAmount > $remainingBalance + 0.001) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => "Payment amount (₹" . number_format($paymentAmount, 2) . ") exceeds remaining unpaid balance of ₹" . number_format($remainingBalance, 2) . "."], 422);
            }
            return back()->withInput()->withErrors(['amount' => "Payment amount (₹" . number_format($paymentAmount, 2) . ") exceeds remaining unpaid balance of ₹" . number_format($remainingBalance, 2) . "."]);
        }

        $result = DB::transaction(function () use ($invoice, $validated, $paymentAmount) {
            $lockedInvoice = SupplierInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            $companyId = $lockedInvoice->company_id ?? $this->getCompanyId();

            // 1. Record payment transaction voucher in payments table
            $paymentVoucher = Payment::create([
                'company_id' => $companyId,
                'payment_voucher_no' => 'PV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'payable_type' => 'supplier_invoices',
                'payable_id' => $lockedInvoice->id,
                'payment_date' => $validated['payment_date'],
                'amount' => $paymentAmount,
                'payment_mode' => $validated['payment_mode'],
                'reference_number' => $validated['reference_number'] ?? null,
                'paid_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // 2. Update SupplierInvoice paid_amount and status
            $newPaidAmount = round((float) $lockedInvoice->paid_amount + $paymentAmount, 2);
            $newStatus = ($newPaidAmount >= (float) $lockedInvoice->total_amount - 0.001) ? 'paid' : 'partially_paid';

            $lockedInvoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            // 3. Post Balanced GL Journal Entry: Dr 2020 (Accounts Payable), Cr 1010 (Bank Account)
            $apAccount = Account::where('company_id', $companyId)->where('code', '2020')->first();
            $bankAccount = Account::where('company_id', $companyId)->where('code', '1010')->first();

            if ($apAccount && $bankAccount && $paymentAmount > 0) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => $validated['payment_date'],
                    'narration' => "Supplier payment for {$lockedInvoice->invoice_number} via {$validated['payment_mode']}",
                    'total_amount' => $paymentAmount,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit Accounts Payable (reduces liability)
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $apAccount->id,
                    'debit' => $paymentAmount,
                    'credit' => 0.00,
                    'description' => "Settlement of invoice {$lockedInvoice->invoice_number}",
                ]);

                // Credit Bank Account (reduces asset)
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $bankAccount->id,
                    'debit' => 0.00,
                    'credit' => $paymentAmount,
                    'description' => "Bank payment for invoice {$lockedInvoice->invoice_number}",
                ]);

                JournalEntry::lockAfterCreation($journalEntry->id);
            }

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'action' => 'supplier_invoice_payment_recorded',
                'auditable_type' => SupplierInvoice::class,
                'auditable_id' => $lockedInvoice->id,
                'new_values' => [
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'payment_amount' => $paymentAmount,
                    'total_paid' => $newPaidAmount,
                    'status' => $newStatus,
                    'voucher_no' => $paymentVoucher->payment_voucher_no,
                ],
            ]);

            return [
                'status' => $newStatus,
                'paid_amount' => $newPaidAmount,
                'voucher_no' => $paymentVoucher->payment_voucher_no,
            ];
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Payment of ₹" . number_format($paymentAmount, 2) . " recorded successfully.",
                'data' => $result,
            ]);
        }

        return back()->with('success', "Payment of ₹" . number_format($paymentAmount, 2) . " recorded under voucher {$result['voucher_no']}.");
    }

    public function supplierInvoices(Request $request): View
    {
        $this->authorizeSupplierInvoice();
        $companyId = $this->getCompanyId();

        $invoices = SupplierInvoice::where('company_id', $companyId)
            ->with(['supplier', 'purchaseOrder'])
            ->latest('invoice_date')
            ->paginate(15);

        return view('procurement.invoices', compact('invoices'));
    }
}
