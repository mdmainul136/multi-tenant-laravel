<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;

class IorCustomsRate extends TenantBaseModel
{
    protected $table = 'ior_customs_rates';

    protected $fillable = [
        'category',
        'rate_percentage',
        'is_active',
    ];

    protected $casts = [
        'rate_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
