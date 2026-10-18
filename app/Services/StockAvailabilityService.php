<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StockLedgerEntry;
use App\Models\Warehouse;

class StockAvailabilityService
{
    /**
     * Get real-time available quantity for a material in a specific warehouse from the stock ledger.
     */
    public static function getWarehouseStock(int $materialId, int $warehouseId): float
    {
        $in = StockLedgerEntry::where('material_id', $materialId)
            ->where('warehouse_id', $warehouseId)
            ->where('direction', 'in')
            ->sum('quantity');

        $out = StockLedgerEntry::where('material_id', $materialId)
            ->where('warehouse_id', $warehouseId)
            ->where('direction', 'out')
            ->sum('quantity');

        return max(0.0, round((float) ($in - $out), 3));
    }

    /**
     * Get total available stock across all active warehouses of the company.
     */
    public static function getCompanyTotalStock(int $materialId, int $companyId): float
    {
        $in = StockLedgerEntry::where('material_id', $materialId)
            ->where('company_id', $companyId)
            ->where('direction', 'in')
            ->sum('quantity');

        $out = StockLedgerEntry::where('material_id', $materialId)
            ->where('company_id', $companyId)
            ->where('direction', 'out')
            ->sum('quantity');

        return max(0.0, round((float) ($in - $out), 3));
    }

    /**
     * Get stock breakdown across all warehouses for a material.
     */
    public static function getWarehouseBreakdown(int $materialId, int $companyId): array
    {
        $warehouses = Warehouse::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $breakdown = [];
        foreach ($warehouses as $wh) {
            $stock = self::getWarehouseStock($materialId, $wh->id);
            if ($stock > 0) {
                $breakdown[] = [
                    'warehouse_id' => $wh->id,
                    'warehouse_name' => $wh->name,
                    'warehouse_type' => $wh->type,
                    'available_stock' => $stock,
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Comprehensive stock analysis for a single Purchase Request line item.
     */
    public static function analyzeLineItem(PurchaseRequestItem $item, ?int $warehouseId = null): array
    {
        $material = $item->material;
        $reqQty = (float) $item->quantity_requested;
        $orderedQty = (float) $item->quantity_ordered;
        $remainingQty = $item->remainingQuantity();
        $uom = $material ? $material->unit_of_measure : 'units';

        $companyId = $item->purchaseRequest?->company_id ?? session('current_company_id');
        $targetWarehouseId = $warehouseId ?? $item->purchaseRequest?->warehouse_id;

        $targetStock = 0.0;
        $hasTargetWarehouse = false;
        if ($targetWarehouseId) {
            $targetStock = self::getWarehouseStock($item->material_id, $targetWarehouseId);
            $hasTargetWarehouse = true;
        }

        $companyTotalStock = $companyId ? self::getCompanyTotalStock($item->material_id, $companyId) : 0.0;
        $otherWarehousesStock = max(0.0, $companyTotalStock - $targetStock);

        // Effective available for shortfall computation
        $effectiveAvailable = $hasTargetWarehouse ? $targetStock : $companyTotalStock;
        $shortfall = max(0.0, round($remainingQty - $effectiveAvailable, 3));

        // Determine clear status
        if (! $hasTargetWarehouse && $companyTotalStock <= 0) {
            $status = 'purchase_required';
            $statusLabel = 'Purchase Required';
            $statusBadgeClass = 'bg-blue-50 text-blue-700 border-blue-200';
        } elseif ($targetStock >= $remainingQty && $remainingQty > 0) {
            $status = 'available';
            $statusLabel = 'Available in Stock';
            $statusBadgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
        } elseif ($targetStock > 0 && $targetStock < $remainingQty) {
            $status = 'partial';
            $statusLabel = 'Partially Available';
            $statusBadgeClass = 'bg-amber-50 text-amber-800 border-amber-200';
        } elseif ($targetStock <= 0 && $otherWarehousesStock > 0) {
            $status = 'transfer_available';
            $statusLabel = 'Available in Central Yard';
            $statusBadgeClass = 'bg-purple-50 text-purple-700 border-purple-200';
        } else {
            $status = 'purchase_required';
            $statusLabel = 'Purchase Required';
            $statusBadgeClass = 'bg-red-50 text-red-700 border-red-200';
        }

        return [
            'item_id' => $item->id,
            'material_id' => $item->material_id,
            'material_name' => $material?->name ?? 'Unknown Material',
            'material_code' => $material?->code ?? '',
            'uom' => $uom,
            'quantity_requested' => $reqQty,
            'quantity_ordered' => $orderedQty,
            'remaining_quantity' => $remainingQty,
            'target_warehouse_stock' => $targetStock,
            'company_total_stock' => $companyTotalStock,
            'other_warehouses_stock' => $otherWarehousesStock,
            'shortfall_quantity' => $shortfall,
            'shortfall' => $shortfall,
            'has_target_warehouse' => $hasTargetWarehouse,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_badge_class' => $statusBadgeClass,
            'can_fulfill_from_stock' => ($targetStock >= $remainingQty && $remainingQty > 0),
            'can_transfer_from_other_warehouse' => ($targetStock < $remainingQty && $otherWarehousesStock > 0),
            'transfer_available' => ($targetStock < $remainingQty && $otherWarehousesStock > 0),
        ];
    }

    /**
     * Analyze entire purchase request for stock availability.
     */
    public static function analyzeRequest(PurchaseRequest $request): array
    {
        $itemsAnalysis = [];
        $totalItems = 0;
        $fullyInStockItems = 0;
        $partiallyInStockItems = 0;
        $transferAvailableItems = 0;
        $purchaseRequiredItems = 0;

        foreach ($request->items as $item) {
            $analysis = self::analyzeLineItem($item, $request->warehouse_id);
            $itemsAnalysis[$item->id] = $analysis;
            $totalItems++;

            if ($analysis['status'] === 'available') {
                $fullyInStockItems++;
            } elseif ($analysis['status'] === 'partial') {
                $partiallyInStockItems++;
            } elseif ($analysis['status'] === 'transfer_available') {
                $transferAvailableItems++;
            } else {
                $purchaseRequiredItems++;
            }
        }

        $overallStatus = 'purchase_required';
        if ($fullyInStockItems === $totalItems && $totalItems > 0) {
            $overallStatus = 'all_in_stock';
        } elseif (($fullyInStockItems + $partiallyInStockItems + $transferAvailableItems) > 0) {
            $overallStatus = 'partial_stock_available';
        }

        return [
            'request_id' => $request->id,
            'warehouse_id' => $request->warehouse_id,
            'total_items' => $totalItems,
            'fully_in_stock_items' => $fullyInStockItems,
            'partially_in_stock_items' => $partiallyInStockItems,
            'transfer_available_items' => $transferAvailableItems,
            'purchase_required_items' => $purchaseRequiredItems,
            'overall_status' => $overallStatus,
            'has_transfer_recommendation' => ($transferAvailableItems > 0 || $fullyInStockItems > 0),
            'items' => $itemsAnalysis,
        ];
    }
}
