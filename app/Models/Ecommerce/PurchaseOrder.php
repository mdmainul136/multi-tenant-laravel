<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrder extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_purchase_orders';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'subtotal',
        'tax',
        'tax_rate',
        'shipping',
        'discount',
        'total',
        'currency',
        'expected_at',
        'sent_at',
        'received_at',
        'shipping_address',
        'notes',
        'admin_note',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'expected_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
