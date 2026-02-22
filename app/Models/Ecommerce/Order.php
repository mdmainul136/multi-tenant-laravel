<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'order_type',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'tax',
        'shipping',
        'discount',
        'total',
        'currency',
        'billing_address',
        'shipping_address',
        'customer_note',
        'admin_note',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
