<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModule extends Model
{
    protected $connection = 'mysql'; // Master database
    
    protected $fillable = [
        'tenant_id',
        'module_id',
        'module_version',
        'subscription_type',
        'status',
        'subscribed_at',
        'expires_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this subscription
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the module for this subscription
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check expiry if set
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Scope to get only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
}
