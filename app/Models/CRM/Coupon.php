<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;

class Coupon extends TenantBaseModel
{
    protected $table = 'ec_coupons';

    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'max_discount',
        'min_order_amount', 'max_uses', 'max_uses_per_customer', 'used_count',
        'applies_to_all', 'applies_to_category', 'is_active', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'value'            => 'decimal:2',
        'max_discount'     => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'applies_to_all'   => 'boolean',
        'is_active'        => 'boolean',
        'starts_at'        => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function uses() { return $this->hasMany(CouponUse::class, 'coupon_id'); }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->max_uses > 0 && $this->used_count >= $this->max_uses) return false;
        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < $this->min_order_amount) return 0.0;

        $discount = match ($this->type) {
            'fixed'        => min($this->value, $subtotal),
            'percent'      => $subtotal * ($this->value / 100),
            'free_shipping'=> 0.0,
            default        => 0.0,
        };

        if ($this->type === 'percent' && $this->max_discount) {
            $discount = min($discount, $this->max_discount);
        }

        return round($discount, 2);
    }

    public function canBeUsedByCustomer(int $customerId): bool
    {
        if ($this->max_uses_per_customer <= 0) return true;
        return $this->uses()->where('customer_id', $customerId)->count() < $this->max_uses_per_customer;
    }

    public function markUsed(int $orderId, int $customerId, float $discountAmount): void
    {
        $this->increment('used_count');
        CouponUse::create([
            'coupon_id'       => $this->id,
            'order_id'        => $orderId,
            'customer_id'     => $customerId,
            'discount_amount' => $discountAmount,
        ]);
    }
}
