<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Refund extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_refunds';

    protected $fillable = [
        'order_id',
        'customer_id',
        'amount',
        'reason',
        'status',
        'refund_method',
        'admin_note',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
