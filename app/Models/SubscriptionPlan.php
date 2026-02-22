<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'plan_key',
        'description',
        'price_monthly',
        'price_yearly',
        'currency',
        'is_active',
        'features',
        'quotas',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'quotas' => 'array',
    ];

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
