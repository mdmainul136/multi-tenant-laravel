<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;

class Customer extends TenantBaseModel
{
    protected $table = 'ec_customers';

    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'country',
        'notes', 'is_active', 'total_orders', 'total_spent',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'total_spent' => 'decimal:2',
    ];

    public function orders()
    {
        return $this->hasMany(\App\Models\Ecommerce\Order::class, 'customer_id');
    }

    public function points()
    {
        return $this->hasOne(CustomerPoints::class, 'customer_id');
    }

    public function couponUses()
    {
        return $this->hasMany(CouponUse::class, 'customer_id');
    }

    public function scopeActive($query)  { return $query->where('is_active', true); }
    public function scopeSearch($query, string $term)
    {
        return $query->where(fn($q) => $q
            ->where('name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%")
            ->orWhere('phone', 'LIKE', "%{$term}%"));
    }
}
