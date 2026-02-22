<?php

namespace App\Modules\Inventory\Services;

use App\Models\Ecommerce\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseInventory;
use App\Models\Inventory\StockLog;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Adjust stock in a specific warehouse.
     */
    public function adjustStock(int $productId, int $warehouseId, int $change, string $type, array $params = []): WarehouseInventory
    {
        $dto = new \App\Modules\Inventory\DTOs\StockAdjustmentDTO(
            product_id: $productId,
            warehouse_id: $warehouseId,
            change: $change,
            type: $type,
            note: $params['note'] ?? null,
            ref_type: $params['ref_type'] ?? null,
            ref_id: $params['ref_id'] ?? null
        );

        return app(\App\Modules\Inventory\Actions\AdjustStockAction::class)->execute($dto);
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transferStock(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, string $note = null): bool
    {
        return app(\App\Modules\Inventory\Actions\TransferStockAction::class)->execute(
            $productId,
            $fromWarehouseId,
            $toWarehouseId,
            $quantity,
            $note
        );
    }

    /**
     * Sync global ec_products.stock_quantity with sum of all warehouse quantities.
     */
    public function syncGlobalStock(int $productId): void
    {
        app(\App\Modules\Inventory\Actions\SyncGlobalStockAction::class)->execute($productId);
    }

    /**
     * Get low stock items (across all warehouses or specific).
     */
    public function getLowStockItems(int $warehouseId = null)
    {
        $query = WarehouseInventory::with(['product', 'warehouse'])
            ->whereRaw('quantity <= alert_quantity');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }
}
