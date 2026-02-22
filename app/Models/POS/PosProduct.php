<?php

namespace App\Models\POS;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosProduct extends TenantBaseModel
{
    use SoftDeletes;

    protected $table = 'pos_products';

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'description',
        'category',
        'price',
        'cost',
        'stock_quantity',
        'min_stock_level',
        'is_active',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'cost'            => 'decimal:2',
        'stock_quantity'  => 'integer',
        'min_stock_level' => 'integer',
        'is_active'       => 'boolean',
    ];
}
