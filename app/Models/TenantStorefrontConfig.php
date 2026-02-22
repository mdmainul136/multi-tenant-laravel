<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantStorefrontConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'theme_id',
        'status',
        'config_json',
        'version',
        'published_at',
        'rollback_from',
    ];

    protected $casts = [
        'config_json' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Get the theme template this config is based on.
     */
    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }
}
