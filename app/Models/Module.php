<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $connection = 'mysql'; // Master database
    
    protected $fillable = [
        'module_key',
        'module_name',
        'description',
        'version',
        'is_active',
        'price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * Get all tenants subscribed to this module
     */
    public function tenantModules()
    {
        return $this->hasMany(TenantModule::class);
    }

    /**
     * Get active subscriptions for this module
     */
    public function activeSubscriptions()
    {
        return $this->hasMany(TenantModule::class)->where('status', 'active');
    }

    /**
     * Scope to get only active modules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
