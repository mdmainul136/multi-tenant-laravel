<?php

namespace App\Models\Marketing;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends TenantBaseModel
{
    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'name',
        'audience_id',
        'channel',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'settings',
        'is_ab_test',
        'ab_test_config',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'settings' => 'json',
        'is_ab_test' => 'boolean',
        'ab_test_config' => 'json',
    ];

    public function audience(): BelongsTo
    {
        return $this->belongsTo(MarketingAudience::class, 'audience_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CampaignVariant::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class, 'campaign_id');
    }
}
