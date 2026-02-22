<?php

namespace App\Models\Finance;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends TenantBaseModel
{
    protected $table = 'ec_finance_accounts';

    protected $fillable = [
        'name',
        'code',
        'type',
        'balance',
        'is_system',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_system' => 'boolean',
    ];

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }
}
