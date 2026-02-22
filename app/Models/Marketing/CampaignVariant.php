<?php

namespace App\Models\Marketing;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignVariant extends TenantBaseModel
{
    protected $table = 'marketing_campaign_variants';

    protected $fillable = [
        'campaign_id',
        'template_id',
        'name',
        'ratio',
    ];

    protected $casts = [
        'ratio' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketingTemplate::class, 'template_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class, 'variant_id');
    }
}
