<?php

namespace App\Modules\Inventory\Actions;

use App\Models\Inventory\WarehouseInventory;
use App\Models\Inventory\StockLog;
use App\Modules\Inventory\DTOs\StockAdjustmentDTO;
use Illuminate\Support\Facades\DB;

class AdjustStockAction
{
    public function execute(StockAdjustmentDTO $dto): WarehouseInventory
    {
        return DB::transaction(function () use ($dto) {
            $inventory = WarehouseInventory::firstOrCreate(
                ['product_id' => $dto->product_id, 'warehouse_id' => $dto->warehouse_id],
                ['quantity' => 0]
            );

            $inventory->increment('quantity', $dto->change);
            $newBalance = $inventory->quantity;

            StockLog::create([
                'product_id' => $dto->product_id,
                'warehouse_id' => $dto->warehouse_id,
                'change' => $dto->change,
                'balance_after' => $newBalance,
                'type' => $dto->type,
                'reference_type' => $dto->ref_type,
                'reference_id' => $dto->ref_id,
                'note' => $dto->note,
                'user_id' => auth()->id(),
            ]);

            // Sync global product stock (Total across all warehouses)
            app(SyncGlobalStockAction::class)->execute($dto->product_id);

            return $inventory;
        });
    }
}
