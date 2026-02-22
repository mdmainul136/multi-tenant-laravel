<?php

namespace App\Models\Finance;

use App\Models\TenantBaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends TenantBaseModel
{
    protected $table = 'ec_finance_transactions';

    protected $fillable = [
        'transaction_number',
        'date',
        'amount',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
