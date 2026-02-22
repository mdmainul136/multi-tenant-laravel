<?php

namespace App\Models\Marketing;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingAudience extends TenantBaseModel
{
    protected $table = 'marketing_audiences';

    protected $fillable = [
        'name',
        'description',
        'type',
        'rules',
        'is_active',
    ];

    protected $casts = [
        'rules' => 'json',
        'is_active' => 'boolean',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'audience_id');
    }
}
