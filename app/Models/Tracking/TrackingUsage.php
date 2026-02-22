<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingUsage extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_usage';

    protected $fillable = [
        'container_id',
        'date',
        'events_received',
        'events_forwarded',
        'events_dropped',
        'power_ups_invoked',
    ];

    protected $casts = [
        'date' => 'date',
        'events_received' => 'integer',
        'events_forwarded' => 'integer',
        'events_dropped' => 'integer',
        'power_ups_invoked' => 'integer',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
