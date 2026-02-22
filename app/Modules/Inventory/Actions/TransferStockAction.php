<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\DTOs\StockAdjustmentDTO;
use Illuminate\Support\Facades\DB;

class TransferStockAction
{
    public function execute(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, ?string $note = null): bool
    {
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $note) {
            $adjustAction = app(AdjustStockAction::class);

            // Deduct from source
            $adjustAction->execute(new StockAdjustmentDTO(
                product_id: $productId,
                warehouse_id: $fromWarehouseId,
                change: -$quantity,
                type: 'transfer_out',
                note: $note
            ));
            
            // Add to destination
            $adjustAction->execute(new StockAdjustmentDTO(
                product_id: $productId,
                warehouse_id: $toWarehouseId,
                change: $quantity,
                type: 'transfer_in',
                note: $note
            ));

            return true;
        });
    }
}
