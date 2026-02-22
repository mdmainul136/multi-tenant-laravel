<?php

namespace App\Models\Inventory;

use App\Models\TenantBaseModel;
use App\Models\Ecommerce\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventory extends TenantBaseModel
{
    protected $table = 'ec_warehouse_inventory';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'quantity',
        'alert_quantity',
        'bin_location',
    ];

    protected $casts = [
        'quantity'       => 'integer',
        'alert_quantity' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
