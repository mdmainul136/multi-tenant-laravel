<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingContainer extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_containers';

    protected $fillable = [
        'name',
        'container_id',
        'domain',
        'preview_url',
        'is_active',
        'settings',
        'power_ups',
        'docker_container_id',
        'docker_status',
        'docker_port',
        'provisioned_at',
        'event_mappings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'power_ups' => 'array',
        'event_mappings' => 'array',
        'provisioned_at' => 'datetime',
        'docker_port' => 'integer',
    ];

    public function destinations()
    {
        return $this->hasMany(TrackingDestination::class, 'container_id');
    }

    public function eventLogs()
    {
        return $this->hasMany(TrackingEventLog::class, 'container_id');
    }
}
