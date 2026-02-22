<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;

class LoyaltyProgram extends TenantBaseModel
{
    protected $table = 'loyalty_programs';

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
        'points_per_currency_unit' => 'decimal:2',
        'point_value'              => 'decimal:4',
        'is_active'                => 'boolean',
    ];

    public function calculateEarnedPoints(float $amount): int
    {
        if (!$this->is_active) return 0;
        return (int) ($amount * $this->points_per_currency_unit);
    }
}
