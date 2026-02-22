<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_customers';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'billing_address',
        'shipping_address',
        'city',
        'state',
        'country',
        'postal_code',
        'total_orders',
        'total_spent',
        'is_active',
    ];

    protected $casts = [
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the customer's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
}
