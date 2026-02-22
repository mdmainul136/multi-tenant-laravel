<?php

namespace App\Modules\Manufacturing\Actions;

use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Inventory\WarehouseInventory;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class StartManufacturingOrderAction
{
    public function execute(int $moId): ManufacturingOrder
    {
        return DB::transaction(function () use ($moId) {
            $mo = ManufacturingOrder::with('bom.items.rawMaterial')->findOrFail($moId);

            if ($mo->status !== 'planned') {
                throw new \Exception("Only planned orders can be started.");
            }

            $inventoryService = app(InventoryService::class);

            // Check & Deduct raw materials
            foreach ($mo->bom->items as $item) {
                $requiredQty = $item->quantity * $mo->target_quantity;
                
                $currentStock = WarehouseInventory::where('product_id', $item->raw_material_id)
                    ->where('warehouse_id', $mo->warehouse_id)
                    ->value('quantity') ?? 0;

                if ($currentStock < $requiredQty) {
                    throw new \Exception("Insufficient raw material stock for: " . ($item->rawMaterial->name ?? 'Product #'.$item->raw_material_id));
                }

                $inventoryService->adjustStock(
                    $item->raw_material_id,
                    $mo->warehouse_id,
                    -(int)$requiredQty,
                    'adjustment',
                    ['note' => "Consumed for MO #{$mo->order_number}", 'ref_type' => 'MO', 'ref_id' => $mo->id]
                );
            }

            $mo->update([
                'status' => 'in_progress',
                'started_at' => now()
            ]);

            return $mo;
        });
    }
}
