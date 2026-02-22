<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_coupons';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount',
        'min_order_amount',
        'max_uses',
        'max_uses_per_customer',
        'used_count',
        'applies_to_all',
        'applies_to_category',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'applies_to_all' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function uses()
    {
        return $this->hasMany(CouponUse::class);
    }
}
