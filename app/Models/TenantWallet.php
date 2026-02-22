<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantWallet extends Model
{
    protected $connection = 'mysql';
    protected $table = 'tenant_wallets';

    protected $fillable = [
        'tenant_id',
        'balance',
        'locked_balance',
        'currency',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'locked_balance' => 'decimal:4',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'tenant_id', 'tenant_id');
    }
}
