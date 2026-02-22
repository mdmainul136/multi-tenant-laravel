<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnItem extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_return_items';

    protected $fillable = [
        'return_id',
        'order_item_id',
        'product_id',
        'product_name',
        'sku',
        'quantity',
        'unit_price',
        'subtotal',
        'condition',
        'restocked',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'restocked' => 'boolean',
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }
}
