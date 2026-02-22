<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingDlq extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_dlq';

    protected $fillable = [
        'container_id',
        'destination_id',
        'destination_type',
        'event_name',
        'event_id',
        'event_payload',
        'credentials',
        'error_message',
        'attempt_count',
        'max_attempts',
        'next_retry_at',
        'last_attempted_at',
        'status',
    ];

    protected $casts = [
        'event_payload' => 'array',
        'credentials' => 'array',
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'last_attempted_at' => 'datetime',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }

    public function destination()
    {
        return $this->belongsTo(TrackingDestination::class, 'destination_id');
    }
}
