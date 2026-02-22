<?php

namespace App\Models\Finance;

use App\Models\TenantBaseModel;

class TaxConfig extends TenantBaseModel
{
    protected $table = 'ec_tax_configs';

    protected $fillable = [
        'name',
        'description',
        'rate',
        'type',
        'applies_to',
        'category_name',
        'is_inclusive',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_inclusive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
