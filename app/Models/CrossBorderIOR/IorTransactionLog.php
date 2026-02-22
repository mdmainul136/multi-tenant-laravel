<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IorTransactionLog extends TenantBaseModel
{
    protected $table = 'ior_transactions_logs';

    protected $fillable = [
        'order_id',
        'transaction_id',
        'payment_method',
        'amount',
        'status',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(IorForeignOrder::class, 'order_id');
    }
}
