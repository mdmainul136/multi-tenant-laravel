<?php

namespace App\Models\Finance;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ledger extends TenantBaseModel
{
    protected $table = 'ec_finance_ledgers';

    protected $fillable = [
        'transaction_id',
        'account_id',
        'type',
        'amount',
        'balance_after',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
