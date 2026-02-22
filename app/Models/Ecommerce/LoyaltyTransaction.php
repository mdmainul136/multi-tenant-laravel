<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyTransaction extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_loyalty_transactions';

    protected $fillable = [
        'customer_id',
        'points',
        'type',
        'reference_type',
        'reference_id',
        'description',
        'balance_after',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
