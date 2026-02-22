<?php

namespace App\Models\FlashSales;

use App\Models\TenantBaseModel;

class FlashSale extends TenantBaseModel
{
    protected $table = 'ec_flash_sales';

    protected $fillable = [
        'tenant_id',
        'name',
        'discount_percentage',
        'product_ids',
        'is_active',
        'starts_at',
        'ends_at'
    ];

    protected $casts = [
        'product_ids' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isActiveNow()
    {
        $now = now();
        return $this->is_active && $this->starts_at <= $now && $this->ends_at >= $now;
    }
}
