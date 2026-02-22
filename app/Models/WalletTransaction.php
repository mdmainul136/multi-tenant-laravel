<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $connection = 'mysql';
    protected $table = 'wallet_transactions';

    protected $fillable = [
        'tenant_id',
        'type',
        'service_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'metadata' => 'json',
    ];

    public function wallet()
    {
        return $this->belongsTo(TenantWallet::class, 'tenant_id', 'tenant_id');
    }
}
