<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogProduct extends TenantBaseModel
{
    use SoftDeletes;

    protected $table = 'catalog_products';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'price_bdt',
        'currency',
        'thumbnail_url',
        'images',
        'brand',
        'sku',
        'availability',
        'status',
        'product_type',
        'attributes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_bdt' => 'decimal:2',
        'images' => 'array',
        'attributes' => 'array',
    ];
}
