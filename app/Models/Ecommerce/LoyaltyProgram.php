<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyProgram extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_loyalty_programs';

    protected $fillable = [
        'name',
        'points_per_currency_unit',
        'min_redeem_points',
        'point_value',
        'points_expiry_days',
        'is_active',
        'terms',
    ];

    protected $casts = [
        'points_per_currency_unit' => 'decimal:4',
        'point_value' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
