<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUse extends TenantBaseModel
{
    protected $table = 'ec_coupon_uses';

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
