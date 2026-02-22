<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingDestination extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_destinations';

    protected $fillable = [
        'container_id',
        'type',
        'name',
        'credentials',
        'mappings',
        'is_active',
        'is_gateway',
    ];

    protected $casts = [
        'credentials' => 'array',
        'mappings' => 'array',
        'is_active' => 'boolean',
        'is_gateway' => 'boolean',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
