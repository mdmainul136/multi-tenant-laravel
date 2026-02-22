<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnRequest extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_returns';

    protected $fillable = [
        'return_number',
        'order_id',
        'customer_id',
        'status',
        'type',
        'reason',
        'reason_detail',
        'refund_method',
        'refund_amount',
        'restock_items',
        'admin_note',
        'approved_at',
        'resolved_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'restock_items' => 'boolean',
        'approved_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
