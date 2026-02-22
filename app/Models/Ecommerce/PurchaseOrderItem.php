<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'sku',
        'quantity',
        'received_quantity',
        'unit_cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
