<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordIorApiConfig extends Model
{
    protected $connection = 'mysql';
    protected $table = 'landlord_ior_api_configs';

    protected $fillable = [
        'provider',
        'api_key',
        'api_secret',
        'supported_regions',
        'cost_per_lookup',
        'is_active',
    ];

    protected $casts = [
        'supported_regions' => 'json',
        'cost_per_lookup' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
