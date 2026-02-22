<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingChannelHealth extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_channel_health';

    protected $fillable = [
        'container_id',
        'channel',
        'date',
        'events_sent',
        'events_succeeded',
        'events_failed',
        'events_retried',
        'avg_latency_ms',
        'p99_latency_ms',
        'error_breakdown',
    ];

    protected $casts = [
        'date' => 'date',
        'events_sent' => 'integer',
        'events_succeeded' => 'integer',
        'events_failed' => 'integer',
        'events_retried' => 'integer',
        'avg_latency_ms' => 'float',
        'p99_latency_ms' => 'float',
        'error_breakdown' => 'array',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
