<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends TenantBaseModel
{
    use HasFactory;
    
    protected $table = 'ec_products';

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'category',
        'price',
        'sale_price',
        'cost',
        'stock_quantity',
        'weight',
        'dimensions',
        'image_url',
        'gallery',
        'is_featured',
        'is_active',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'gallery' => 'array',
    ];

    public function categoryData()
    {
        return $this->belongsTo(Category::class, 'category', 'slug');
    }

    /**
     * Get current price (sale price if active and lower)
     */
    public function getCurrentPrice()
    {
        if ($this->sale_price && $this->sale_price < $this->price) {
            return $this->sale_price;
        }
        return $this->price;
    }
}
