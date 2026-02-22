<?php

namespace App\Models\POS;

use App\Models\TenantBaseModel;
use App\Models\User;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends TenantBaseModel
{
    protected $table = 'pos_sessions';

    protected $fillable = [
        'user_id',
        'branch_id',
        'warehouse_id',
        'opening_balance',
        'closing_balance',
        'cash_transactions_total',
        'card_transactions_total',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_balance'         => 'decimal:2',
        'closing_balance'         => 'decimal:2',
        'cash_transactions_total' => 'decimal:2',
        'card_transactions_total' => 'decimal:2',
        'opened_at'               => 'datetime',
        'closed_at'               => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class, 'session_id');
    }
}
