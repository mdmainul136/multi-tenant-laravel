<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BNPLTransaction extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'payment_id', 'provider', 'external_id', 'checkout_url',
        'instalments_count', 'merchant_amount', 'fee_amount', 'status',
    ];

    public function payment() { return $this->belongsTo(Payment::class); }
}
