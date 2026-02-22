<?php

namespace App\Modules\Inventory\Actions;

use App\Models\Ecommerce\Product;
use App\Models\Inventory\WarehouseInventory;

class SyncGlobalStockAction
{
    public function execute(int $productId): void
    {
        $total = WarehouseInventory::where('product_id', $productId)->sum('quantity');
        Product::where('id', $productId)->update(['stock_quantity' => $total]);
    }
}
