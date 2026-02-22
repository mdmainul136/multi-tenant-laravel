<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'renews_at',
        'canceled_at',
        'ends_at',
        'auto_renew',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'renews_at' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    /**
     * The tenant who owns this subscription
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The plan this subscription is for
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Check if the subscription is currently active (not expired or canceled)
     */
    public function isActive(): bool
    {
        if ($this->status === 'canceled' && $this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->status === 'expired') {
            return false;
        }

        // If in trial
        if ($this->trial_ends_at && $this->trial_ends_at->isFuture()) {
            return true;
        }

        // If active and not expired
        return $this->status === 'active' && (!$this->ends_at || $this->ends_at->isFuture());
    }
}
