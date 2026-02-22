<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends TenantBaseModel
{
    protected $table = 'loyalty_transactions';

    protected $fillable = [
        'customer_id',
        'points',
        'type', // earn, redeem, adjust, expire
        'description',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
