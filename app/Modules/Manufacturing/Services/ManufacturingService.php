<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Inventory\WarehouseInventory;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class ManufacturingService
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Create a Bill of Materials.
     */
    public function createBom(int $productId, string $name, array $items): Bom
    {
        $dto = new \App\Modules\Manufacturing\DTOs\BomDTO(
            finished_product_id: $productId,
            name: $name,
            items: $items
        );

        return app(\App\Modules\Manufacturing\Actions\CreateBomAction::class)->execute($dto);
    }

    /**
     * Start a Manufacturing Order.
     * Checks if enough raw materials are available.
     */
    public function startMO(int $moId): ManufacturingOrder
    {
        return app(\App\Modules\Manufacturing\Actions\StartManufacturingOrderAction::class)->execute($moId);
    }

    /**
     * Complete a Manufacturing Order.
     * Adds finished products to inventory.
     */
    public function completeMO(int $moId, int $actualQuantityProduced): ManufacturingOrder
    {
        return app(\App\Modules\Manufacturing\Actions\CompleteManufacturingOrderAction::class)->execute($moId, $actualQuantityProduced);
    }
}
