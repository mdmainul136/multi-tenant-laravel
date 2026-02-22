<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_carts';

    protected $fillable = [
        'customer_id',
        'session_id',
        'items',
        'subtotal',
        'tax',
        'discount',
        'total',
        'coupon_code',
        'expires_at',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
