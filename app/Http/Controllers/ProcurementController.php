<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::with(['supplier', 'project', 'creator'])
            ->latest()
            ->paginate(10);

        $suppliers = Supplier::withCount('purchaseOrders')->get();

        $stats = [
            'total_pos' => PurchaseOrder::count(),
            'pending_pos' => PurchaseOrder::where('status', 'draft')->count(),
            'total_po_value' => PurchaseOrder::sum('total_amount'),
        ];

        return view('procurement.index', compact('orders', 'suppliers', 'stats'));
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

    public function createPO(): View
    {
        $this->authorizeDirectPO();

        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get();
        $projects = Project::whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();
        $materials = Material::where('is_active', true)->orderBy('name')->get();

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
            'terms_and_conditions' => 'nullable|string',
        ]);

        $subtotal = $validated['quantity'] * $validated['unit_price'];
        $tax = $subtotal * 0.18; // Standard 18% GST for industrial construction materials
        $total = $subtotal + $tax;

        $po = PurchaseOrder::create([
            'supplier_id' => $validated['supplier_id'],
            'project_id' => $validated['project_id'],
            'po_number' => 'PO-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'po_date' => $validated['po_date'],
            'delivery_due_date' => $validated['delivery_due_date'],
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'status' => 'approved',
            'terms_and_conditions' => $validated['terms_and_conditions'],
            'created_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'material_id' => $validated['material_id'],
            'quantity_ordered' => $validated['quantity'],
            'unit_price' => $validated['unit_price'],
            'tax_percent' => 18.00,
            'total_price' => $total,
        ]);

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
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

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

        DB::transaction(function () use ($validated, $order) {
            $grn = GoodsReceipt::create([
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

        SupplierInvoice::create([
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
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        return redirect()->route('procurement.show', $order)
            ->with('success', "Supplier Invoice {$validated['invoice_number']} recorded for payment matching.");
    }

    public function supplierInvoices(Request $request): View
    {
        $this->authorizeSupplierInvoice();

        $invoices = SupplierInvoice::with(['supplier', 'purchaseOrder'])
            ->latest('invoice_date')
            ->paginate(15);

        return view('procurement.invoices', compact('invoices'));
    }
}
