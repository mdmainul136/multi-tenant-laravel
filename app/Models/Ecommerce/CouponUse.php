<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CouponUse extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_coupon_uses';

    protected $fillable = [
        'coupon_id',
        'order_id',
        'customer_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
