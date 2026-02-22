<?php

namespace App\Models\Inventory;

use App\Models\TenantBaseModel;
use App\Models\Ecommerce\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends TenantBaseModel
{
    protected $table = 'ec_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity'  => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal'  => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
