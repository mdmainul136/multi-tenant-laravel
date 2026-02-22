<?php

namespace App\Models\POS;

use App\Models\TenantBaseModel;
use App\Models\User;
use App\Models\Ecommerce\Customer;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends TenantBaseModel
{
    protected $table = 'pos_sales';

    protected $fillable = [
        'sale_number',
        'session_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'subtotal',
        'tax',
        'discount',
        'total',
        'cash_received',
        'change_amount',
        'points_earned',
        'points_redeemed',
        'payment_method',
        'payment_status',
        'notes',
        'sold_by',
        'offline_id',
        'zatca_qr',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'tax'             => 'decimal:2',
        'discount'        => 'decimal:2',
        'total'           => 'decimal:2',
        'cash_received'   => 'decimal:2',
        'change_amount'   => 'decimal:2',
        'points_earned'   => 'integer',
        'points_redeemed' => 'integer',
    ];

    /**
     * Relationship: Sale belongs to a Session.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    /**
     * Relationship: Sale was made by a User.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /**
     * Relationship: Sale has many items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'sale_id');
    }

    /**
     * Relationship: Sale may have a customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relationship: Sale may have many payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'sale_id');
    }

    /**
     * Relationship: Sale belongs to a Branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relationship: Sale involved a Warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
