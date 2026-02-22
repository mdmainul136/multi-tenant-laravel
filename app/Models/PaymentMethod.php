<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'stripe_payment_method_id',
        'type',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Set as default payment method
     */
    public function setAsDefault()
    {
        // Remove default from all other payment methods for this tenant
        self::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Check if payment method is expired
     */
    public function isExpired()
    {
        if (!$this->exp_month || !$this->exp_year) {
            return false;
        }

        $expiryDate = \Carbon\Carbon::createFromDate($this->exp_year, $this->exp_month, 1)->endOfMonth();
        return $expiryDate->isPast();
    }

    /**
     * Get card display name
     */
    public function getDisplayNameAttribute()
    {
        $brand = ucfirst($this->brand ?? 'Card');
        return "{$brand} •••• {$this->last4}";
    }

    /**
     * Get expiry display
     */
    public function getExpiryDisplayAttribute()
    {
        if (!$this->exp_month || !$this->exp_year) {
            return null;
        }

        return sprintf('%02d/%d', $this->exp_month, $this->exp_year);
    }

    /**
     * Scope for default payment method
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get brand icon class
     */
    public function getBrandIconAttribute()
    {
        $icons = [
            'visa' => 'fab fa-cc-visa',
            'mastercard' => 'fab fa-cc-mastercard',
            'amex' => 'fab fa-cc-amex',
            'discover' => 'fab fa-cc-discover',
        ];

        return $icons[strtolower($this->brand)] ?? 'fas fa-credit-card';
    }
}
