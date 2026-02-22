<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingAttribution extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_attribution';

    protected $fillable = [
        'container_id',
        'visitor_id',
        'session_id',
        'channel',
        'event_name',
        'campaign',
        'source',
        'medium',
        'click_id',
        'click_id_type',
        'is_conversion',
        'conversion_value',
        'currency',
        'touched_at',
    ];

    protected $casts = [
        'is_conversion' => 'boolean',
        'conversion_value' => 'decimal:2',
        'touched_at' => 'datetime',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
