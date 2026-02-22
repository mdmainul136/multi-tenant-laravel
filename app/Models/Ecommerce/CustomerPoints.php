<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerPoints extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_customer_points';

    protected $fillable = [
        'customer_id',
        'points_balance',
        'lifetime_earned',
        'lifetime_redeemed',
        'last_activity_at',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'lifetime_earned' => 'integer',
        'lifetime_redeemed' => 'integer',
        'last_activity_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
