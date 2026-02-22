<?php

namespace App\Models\POS;

use App\Models\TenantBaseModel;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosHeldSale extends TenantBaseModel
{
    protected $table = 'pos_held_sales';

    protected $fillable = [
        'user_id',
        'branch_id',
        'customer_name',
        'cart_data',
        'notes',
        'hold_reference',
    ];

    protected $casts = [
        'cart_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
