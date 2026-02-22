<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletTransaction extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_wallet_transactions';

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
