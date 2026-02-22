<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Currency extends TenantBaseModel
{
    use HasFactory;

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
}
