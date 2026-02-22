<?php

namespace App\Models\Marketing;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingTemplate extends TenantBaseModel
{
    protected $table = 'marketing_templates';

    protected $fillable = [
        'name',
        'channel',
        'subject',
        'content',
        'placeholders',
    ];

    protected $casts = [
        'placeholders' => 'json',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(CampaignVariant::class, 'template_id');
    }
}
