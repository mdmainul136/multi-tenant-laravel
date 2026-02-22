<?php

namespace App\Models\Loyalty;

use App\Models\TenantBaseModel;

class LoyaltyProgram extends TenantBaseModel
{
    protected $table = 'ec_loyalty_programs';

    protected $fillable = [
        'tenant_id',
        'points_per_currency',
        'currency_per_point',
        'min_redemption_points',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'points_per_currency' => 'float',
        'currency_per_point' => 'float',
    ];
}
