<?php

namespace App\Modules\Manufacturing\Actions;

use App\Models\Manufacturing\ManufacturingOrder;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class CompleteManufacturingOrderAction
{
    public function execute(int $moId, int $producedQuantity): ManufacturingOrder
    {
        return DB::transaction(function () use ($moId, $producedQuantity) {
            $mo = ManufacturingOrder::findOrFail($moId);

            if ($mo->status !== 'in_progress') {
                throw new \Exception("Only in-progress orders can be completed.");
            }

            $inventoryService = app(InventoryService::class);

            // Add finished goods
            $inventoryService->adjustStock(
                $mo->finished_product_id,
                $mo->warehouse_id,
                $producedQuantity,
                'adjustment',
                ['note' => "Produced from MO #{$mo->order_number}", 'ref_type' => 'MO', 'ref_id' => $mo->id]
            );

            $mo->update([
                'status' => 'completed',
                'produced_quantity' => $producedQuantity,
                'finished_at' => now()
            ]);

            return $mo;
        });
    }
}
