<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IorShipmentBatch extends TenantBaseModel
{
    protected $table = 'ior_shipment_batches';

    protected $fillable = [
        'batch_number',
        'courier_name',
        'master_tracking_number',
        'status', // pending, in_transit, customs, received, dispatched
        'shipment_type', // air, sea
        'estimated_delivery',
        'total_weight',
        'notes',
    ];

    protected $casts = [
        'estimated_delivery' => 'date',
        'total_weight'       => 'decimal:3',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(IorForeignOrder::class, 'batch_id');
    }
}
