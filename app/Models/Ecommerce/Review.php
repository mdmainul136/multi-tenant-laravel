<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_reviews';

    protected $fillable = [
        'product_id',
        'customer_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'is_verified',
        'is_approved',
        'helpful_count',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_approved' => 'boolean',
        'rating' => 'integer',
        'helpful_count' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
