<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Company;
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
    protected function getCompanyId(): int
    {
        return (int) (session('current_company_id')
            ?? Auth::user()?->currentCompany()?->id
            ?? Company::configuredCompanyId()
            ?? Company::first()?->id
            ?? 1);
    }

    public function index(Request $request): View
    {
        $companyId = $this->getCompanyId();

        $materials = Material::where('company_id', $companyId)
            ->with(['category', 'ledgerEntries'])
            ->latest()
            ->paginate(12);

        $warehouses = Warehouse::where('company_id', $companyId)->with(['site'])->get();

        $stats = [
            'total_materials' => Material::where('company_id', $companyId)->count(),
            'total_warehouses' => Warehouse::where('company_id', $companyId)->count(),
            'low_stock' => Material::where('company_id', $companyId)->get()->filter(fn ($m) => $m->currentStock() <= $m->reorder_level)->count(),
        ];

        return view('inventory.index', compact('materials', 'warehouses', 'stats'));
    }

    public function ledger(Request $request): View
    {
        $companyId = $this->getCompanyId();

        $entries = StockLedgerEntry::where('company_id', $companyId)
            ->with(['material', 'warehouse', 'creator'])
            ->latest('entry_date')
            ->paginate(20);

        return view('inventory.ledger', compact('entries'));
    }

    protected function authorizeStockTransfer(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $canTransfer = $user && ($user->hasPermission('*') || $user->hasPermission('inventory.*') || in_array($roleSlug, ['owner', 'admin', 'storekeeper', 'procurement_officer']));
        if (! $canTransfer) {
            abort(403, 'Unauthorized. Stock transfers are restricted to Storekeepers, Procurement Officers, and Administrators.');
        }
    }

    public function createTransfer(): View
    {
        $this->authorizeStockTransfer();
        $companyId = $this->getCompanyId();

        $warehouses = Warehouse::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $materials = Material::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        return view('inventory.create-transfer', compact('warehouses', 'materials'));
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $this->authorizeStockTransfer();
        $companyId = $this->getCompanyId();

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

        $material = Material::where('id', $validated['material_id'])->where('company_id', $companyId)->firstOrFail();
        $sourceWarehouse = Warehouse::where('id', $validated['from_warehouse_id'])->where('company_id', $companyId)->first();
        $targetWarehouse = Warehouse::where('id', $validated['to_warehouse_id'])->where('company_id', $companyId)->first();

        if (! $sourceWarehouse || ! $targetWarehouse) {
            abort(403, 'Both source and target warehouses must belong to the active company workspace.');
        }

        // Atomic double-entry stock movement with concurrency locking
        $result = DB::transaction(function () use ($validated, $material, $sourceWarehouse, $targetWarehouse, $companyId) {
            // Check stock availability in source warehouse with locking
            $inStock = (float) StockLedgerEntry::where('material_id', $material->id)
                ->where('warehouse_id', $sourceWarehouse->id)
                ->where('company_id', $companyId)
                ->where('direction', 'in')
                ->lockForUpdate()
                ->sum('quantity');

            $outStock = (float) StockLedgerEntry::where('material_id', $material->id)
                ->where('warehouse_id', $sourceWarehouse->id)
                ->where('company_id', $companyId)
                ->where('direction', 'out')
                ->lockForUpdate()
                ->sum('quantity');

            $availableStock = $inStock - $outStock;

            if ($availableStock < (float) $validated['quantity']) {
                return [
                    'success' => false,
                    'message' => "Insufficient stock in {$sourceWarehouse->name}. Available: {$availableStock} {$material->unit_of_measure}, Requested: {$validated['quantity']}",
                ];
            }

            $transfer = StockTransfer::create([
                'company_id' => $companyId,
                'transfer_number' => 'TRF-' . strtoupper(Str::random(8)),
                'from_warehouse_id' => $sourceWarehouse->id,
                'to_warehouse_id' => $targetWarehouse->id,
                'transfer_date' => $validated['transfer_date'],
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
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
                'company_id' => $companyId,
                'material_id' => $material->id,
                'warehouse_id' => $sourceWarehouse->id,
                'entry_date' => $validated['transfer_date'],
                'transaction_type' => 'transfer_out',
                'reference_type' => 'stock_transfers',
                'reference_id' => $transfer->id,
                'quantity' => $validated['quantity'],
                'direction' => 'out',
                'unit_rate' => $material->standard_cost,
                'total_amount' => round($validated['quantity'] * $material->standard_cost, 2),
                'balance_quantity' => $availableStock - $validated['quantity'],
                'created_by' => Auth::id(),
            ]);

            // Inflow to destination warehouse
            $targetStock = (float) StockLedgerEntry::where('material_id', $material->id)
                ->where('warehouse_id', $targetWarehouse->id)
                ->where('company_id', $companyId)
                ->where('direction', 'in')->sum('quantity') -
                (float) StockLedgerEntry::where('material_id', $material->id)
                ->where('warehouse_id', $targetWarehouse->id)
                ->where('company_id', $companyId)
                ->where('direction', 'out')->sum('quantity');

            StockLedgerEntry::create([
                'company_id' => $companyId,
                'material_id' => $material->id,
                'warehouse_id' => $targetWarehouse->id,
                'entry_date' => $validated['transfer_date'],
                'transaction_type' => 'transfer_in',
                'reference_type' => 'stock_transfers',
                'reference_id' => $transfer->id,
                'quantity' => $validated['quantity'],
                'direction' => 'in',
                'unit_rate' => $material->standard_cost,
                'total_amount' => round($validated['quantity'] * $material->standard_cost, 2),
                'balance_quantity' => $targetStock + $validated['quantity'],
                'created_by' => Auth::id(),
            ]);

            $remainingStock = $availableStock - $validated['quantity'];
            if ($remainingStock <= (float) $material->reorder_level) {
                AppNotification::create([
                    'company_id' => $companyId,
                    'user_id' => null,
                    'title' => 'Material Stock Shortage Alert',
                    'message' => "Warning: Stock for {$material->name} (" . ($material->code ?? 'N/A') . ") in {$sourceWarehouse->name} is at {$remainingStock} {$material->unit_of_measure} (reorder level: {$material->reorder_level}).",
                    'type' => 'warning',
                    'action_url' => route('inventory.index'),
                    'is_read' => false,
                ]);
            }

            return ['success' => true];
        });

        if (! $result['success']) {
            return back()->withInput()->withErrors(['quantity' => $result['message']]);
        }

        return redirect()->route('inventory.ledger')->with('success', "Transfer of {$validated['quantity']} {$material->unit_of_measure} dispatched and recorded atomically.");
    }
}
