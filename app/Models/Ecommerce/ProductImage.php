<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductImage extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_product_images';

    protected $fillable = [
        'product_id',
        'url',
        'disk',
        'path',
        'alt_text',
        'title',
        'sort_order',
        'is_primary',
        'file_size',
        'width',
        'height',
        'mime_type',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
