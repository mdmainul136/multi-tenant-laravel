<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IorWarehouse extends TenantBaseModel
{
    protected $table = 'ior_warehouses';

    protected $fillable = [
        'name',
        'location_type', // source, transit, destination
        'address',
        'contact_person',
        'contact_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function currentOrders(): HasMany
    {
        return $this->hasMany(IorForeignOrder::class, 'current_warehouse_id');
    }
}
