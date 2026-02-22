<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;

class IorHsCode extends TenantBaseModel
{
    protected $table = 'ior_hs_codes';

    protected $fillable = [
        'hs_code',
        'category_name',
        'description',
        'duty_rate',
        'vat_rate',
        'ait_rate',
        'is_active',
    ];

    protected $casts = [
        'duty_rate' => 'decimal:2',
        'vat_rate'  => 'decimal:2',
        'ait_rate'  => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
