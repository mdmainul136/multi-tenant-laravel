<?php

namespace App\Models\Tracking;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrackingTag extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_tracking_tags';

    protected $fillable = [
        'container_id',
        'name',
        'type',
        'destination_type',
        'config',
        'triggers',
        'variables',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'config' => 'array',
        'triggers' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function container()
    {
        return $this->belongsTo(TrackingContainer::class, 'container_id');
    }
}
