<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $materials = Material::with(['category', 'ledgerEntries'])
            ->latest()
            ->paginate(12);

        $warehouses = Warehouse::with(['site'])->get();

        $stats = [
            'total_materials' => Material::count(),
            'total_warehouses' => Warehouse::count(),
            'low_stock' => Material::all()->filter(fn ($m) => $m->currentStock() <= $m->reorder_level)->count(),
        ];

        return view('inventory.index', compact('materials', 'warehouses', 'stats'));
    }

    public function ledger(Request $request): View
    {
        $entries = StockLedgerEntry::with(['material', 'warehouse', 'creator'])
            ->latest('entry_date')
            ->paginate(20);

        return view('inventory.ledger', compact('entries'));
    }

    public function createTransfer(): View
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $materials = Material::orderBy('name')->get();

        return view('inventory.create-transfer', compact('warehouses', 'materials'));
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id|different:to_warehouse_id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'transfer_date' => 'required|date',
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'vehicle_number' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $material = Material::findOrFail($validated['material_id']);
        $sourceWarehouse = Warehouse::findOrFail($validated['from_warehouse_id']);
        $targetWarehouse = Warehouse::findOrFail($validated['to_warehouse_id']);

        // Check stock availability in source warehouse
        $inStock = StockLedgerEntry::where('material_id', $material->id)
            ->where('warehouse_id', $sourceWarehouse->id)
            ->where('direction', 'in')
            ->sum('quantity');

        $outStock = StockLedgerEntry::where('material_id', $material->id)
            ->where('warehouse_id', $sourceWarehouse->id)
            ->where('direction', 'out')
            ->sum('quantity');

        $availableStock = $inStock - $outStock;

        if ($availableStock < $validated['quantity']) {
            return back()->withInput()->withErrors([
                'quantity' => "Insufficient stock in {$sourceWarehouse->name}. Available: {$availableStock} {$material->unit_of_measure}, Requested: {$validated['quantity']}",
            ]);
        }

        // Atomic double-entry stock movement
        DB::transaction(function () use ($validated, $material, $sourceWarehouse, $targetWarehouse, $availableStock) {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF-' . strtoupper(Str::random(8)),
                'from_warehouse_id' => $sourceWarehouse->id,
                'to_warehouse_id' => $targetWarehouse->id,
                'transfer_date' => $validated['transfer_date'],
                'vehicle_number' => $validated['vehicle_number'],
                'driver_name' => $validated['driver_name'],
                'notes' => $validated['notes'],
                'status' => 'received',
                'requested_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'received_by' => Auth::id(),
                'received_at' => Carbon::now(),
            ]);

            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'material_id' => $material->id,
                'quantity_dispatched' => $validated['quantity'],
                'quantity_received' => $validated['quantity'],
            ]);

            // Outflow from source warehouse
            StockLedgerEntry::create([
                'material_id' => $material->id,
                'warehouse_id' => $sourceWarehouse->id,
                'entry_date' => $validated['transfer_date'],
                'transaction_type' => 'transfer_out',
                'reference_type' => 'stock_transfers',
                'reference_id' => $transfer->id,
                'quantity' => $validated['quantity'],
                'direction' => 'out',
                'unit_rate' => $material->standard_cost,
                'total_amount' => $validated['quantity'] * $material->standard_cost,
                'balance_quantity' => $availableStock - $validated['quantity'],
                'created_by' => Auth::id(),
            ]);

            // Inflow to destination warehouse
            $targetStock = StockLedgerEntry::where('material_id', $material->id)
                ->where('warehouse_id', $targetWarehouse->id)
                ->where('direction', 'in')->sum('quantity') -
                StockLedgerEntry::where('material_id', $material->id)
                ->where('warehouse_id', $targetWarehouse->id)
                ->where('direction', 'out')->sum('quantity');

            StockLedgerEntry::create([
                'material_id' => $material->id,
                'warehouse_id' => $targetWarehouse->id,
                'entry_date' => $validated['transfer_date'],
                'transaction_type' => 'transfer_in',
                'reference_type' => 'stock_transfers',
                'reference_id' => $transfer->id,
                'quantity' => $validated['quantity'],
                'direction' => 'in',
                'unit_rate' => $material->standard_cost,
                'total_amount' => $validated['quantity'] * $material->standard_cost,
                'balance_quantity' => $targetStock + $validated['quantity'],
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('inventory.ledger')->with('success', "Transfer of {$validated['quantity']} {$material->unit_of_measure} dispatched and recorded atomically.");
    }
}
