<?php

namespace App\Models\Inventory;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends TenantBaseModel
{
    protected $table = 'ec_warehouses';

    protected $fillable = [
        'name',
        'code',
        'location',
        'address',
        'phone',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    public function inventory(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(StockLog::class);
    }
}
