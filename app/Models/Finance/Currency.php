<?php

namespace App\Models\Finance;

use App\Models\TenantBaseModel;

class Currency extends TenantBaseModel
{
    protected $table = 'ec_currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate_to_usd',
        'is_default',
        'is_active',
        'rates_updated_at',
    ];

    protected $casts = [
        'exchange_rate_to_usd' => 'decimal:8',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'rates_updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getDefault()
    {
        return self::where('is_default', true)->first() ?? self::first();
    }
}
