<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_product_variants';

    protected $fillable = [
        'product_id',
        'variant_name',
        'sku',
        'price',
        'sale_price',
        'stock_quantity',
        'attributes',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
