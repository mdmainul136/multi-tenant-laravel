<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingConsent extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_consent';

    protected $fillable = [
        'container_id',
        'visitor_id',
        'analytics',
        'marketing',
        'functional',
        'personalization',
        'consent_source',
        'ip_address',
        'user_agent',
        'raw_consent',
        'consented_at',
        'expires_at',
    ];

    protected $casts = [
        'analytics' => 'boolean',
        'marketing' => 'boolean',
        'functional' => 'boolean',
        'personalization' => 'boolean',
        'raw_consent' => 'array',
        'consented_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
