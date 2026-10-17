<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function createPO(): View
    {
        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get();
        $projects = Project::whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();
        $materials = Material::where('is_active', true)->orderBy('name')->get();

        return view('procurement.create-po', compact('suppliers', 'projects', 'materials'));
    }

    public function storePO(Request $request): RedirectResponse
    {
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
        $order->load(['supplier', 'project', 'items.material', 'creator', 'approver']);

        return view('procurement.show', compact('order'));
    }

    public function supplierInvoices(Request $request): View
    {
        $invoices = SupplierInvoice::with(['supplier', 'purchaseOrder'])
            ->latest('invoice_date')
            ->paginate(15);

        return view('procurement.invoices', compact('invoices'));
    }
}
