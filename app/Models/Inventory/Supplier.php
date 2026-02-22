<?php

namespace App\Models\Inventory;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends TenantBaseModel
{
    protected $table = 'ec_suppliers';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'contact_person',
        'payment_terms',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
