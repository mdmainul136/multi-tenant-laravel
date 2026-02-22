<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_wallets';

    protected $fillable = [
        'customer_id',
        'balance',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
