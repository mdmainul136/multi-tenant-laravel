<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingEventLog extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_event_logs';

    protected $fillable = [
        'container_id',
        'event_type',
        'source_ip',
        'user_agent',
        'payload',
        'status_code',
    ];

    protected $casts = [
        'payload' => 'array',
        'status_code' => 'integer',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
