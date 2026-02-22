<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;

class IorShippingSetting extends TenantBaseModel
{
    protected $table = 'ior_shipping_settings';

    protected $fillable = [
        'shipping_method',
        'rate_per_kg',
        'min_charge',
        'is_active',
    ];

    protected $casts = [
        'rate_per_kg' => 'decimal:2',
        'min_charge' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
