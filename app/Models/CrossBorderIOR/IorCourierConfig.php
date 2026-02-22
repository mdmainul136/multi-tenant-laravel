<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;

class IorCourierConfig extends TenantBaseModel
{
    protected $table = 'ior_courier_configs';

    protected $fillable = [
        'courier_code',
        'display_name',
        'type',
        'credentials',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
    ];
}
