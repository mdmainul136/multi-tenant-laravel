<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxConfig extends TenantBaseModel
{
    use HasFactory;

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
}
