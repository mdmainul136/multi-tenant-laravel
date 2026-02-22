<?php

namespace App\Models\Inventory;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseOrder extends TenantBaseModel
{
    protected $table = 'ec_purchase_orders';

    const STATUSES = ['draft', 'sent', 'partial', 'received', 'cancelled'];

    protected $fillable = [
        'po_number',
        'supplier_id',
        'warehouse_id',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',
        'expected_date',
        'received_at',
        'sent_at',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'expected_date' => 'date',
        'received_at'   => 'datetime',
        'sent_at'       => 'datetime',
    ];

    public static function generatePoNumber(): string
    {
        return 'PO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function recalculateTotal(): void
    {
        $this->subtotal = $this->items()->sum('subtotal');
        $this->total_amount = $this->subtotal + $this->tax_amount;
        $this->save();
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $map = [
            'draft'     => ['sent', 'cancelled'],
            'sent'      => ['partial', 'received', 'cancelled'],
            'partial'   => ['received', 'cancelled'],
            'received'  => [],
            'cancelled' => [],
        ];

        return in_array($newStatus, $map[$this->status] ?? []);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'partial']);
    }
}
