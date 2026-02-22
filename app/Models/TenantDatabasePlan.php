<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantDatabasePlan extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'slug',
        'storage_limit_gb',
        'max_tables',
        'max_connections',
        'price',
        'is_active',
    ];

    protected $casts = [
        'storage_limit_gb' => 'integer',
        'max_tables' => 'integer',
        'max_connections' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Tenants on this plan
     */
    public function tenants()
    {
        return $this->hasMany(Tenant::class, 'database_plan_id');
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the storage limit in MB
     */
    public function getStorageLimitMbAttribute(): int
    {
        return $this->storage_limit_gb * 1024;
    }
}
