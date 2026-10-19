<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialStockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public int $companyId;
    public int $warehouseId;
    public string $warehouseName;
    public int $materialId;
    public string $materialName;
    public float $currentBalance;
    public string $unitOfMeasure;
    public bool $isLowStock;

    public function __construct(
        int $companyId,
        int $warehouseId,
        string $warehouseName,
        int $materialId,
        string $materialName,
        float $currentBalance,
        string $unitOfMeasure = 'units',
        bool $isLowStock = false
    ) {
        $this->companyId = $companyId;
        $this->warehouseId = $warehouseId;
        $this->warehouseName = $warehouseName;
        $this->materialId = $materialId;
        $this->materialName = $materialName;
        $this->currentBalance = $currentBalance;
        $this->unitOfMeasure = $unitOfMeasure;
        $this->isLowStock = $isLowStock;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.' . $this->companyId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MaterialStockUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->companyId,
            'warehouse_id' => $this->warehouseId,
            'warehouse_name' => $this->warehouseName,
            'material_id' => $this->materialId,
            'material_name' => $this->materialName,
            'current_balance' => round($this->currentBalance, 3),
            'unit_of_measure' => $this->unitOfMeasure,
            'is_low_stock' => $this->isLowStock,
            'updated_at' => now()->toISOString(),
        ];
    }
}
