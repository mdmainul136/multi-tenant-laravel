<?php

namespace App\Models\POS;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends TenantBaseModel
{
    protected $table = 'pos_payments';

    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'transaction_id',
        'details',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'details' => 'array',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'sale_id');
    }
}
