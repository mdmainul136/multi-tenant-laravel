<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IorForeignOrder extends TenantBaseModel
{
    use SoftDeletes;

    protected $table = 'ior_foreign_orders';

    // Status Constants
    const STATUS_PENDING   = 'pending';
    const STATUS_SOURCING  = 'sourcing';
    const STATUS_ORDERED   = 'ordered';
    const STATUS_SHIPPED   = 'shipped';
    const STATUS_CUSTOMS   = 'customs';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'product_url',
        'product_name',
        'product_category',
        'product_weight_kg',
        'product_features',
        'product_specs',
        'quantity',
        'product_variant',
        'product_image_url',
        'source_marketplace',
        'source_price_usd',
        'exchange_rate',
        'base_price_bdt',
        'customs_fee_bdt',
        'shipping_cost_bdt',
        'profit_margin_bdt',
        'estimated_price_bdt',
        'final_price_bdt',
        'advance_amount',
        'remaining_amount',
        'advance_paid',
        'remaining_paid',
        'payment_method',
        'payment_status',
        'shipping_full_name',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'shipping_area',
        'shipping_postal_code',
        'tracking_number',
        'tracking_url',
        'courier_code',
        'courier_order_id',
        'courier_label_url',
        'order_status',
        'admin_note',
        'notes',
        'cancellation_reason',
        'scraped_data',
        'pricing_breakdown',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'product_weight_kg' => 'decimal:3',
        'source_price_usd'  => 'decimal:2',
        'exchange_rate'     => 'decimal:4',
        'base_price_bdt'    => 'decimal:2',
        'customs_fee_bdt'   => 'decimal:2',
        'shipping_cost_bdt' => 'decimal:2',
        'profit_margin_bdt' => 'decimal:2',
        'estimated_price_bdt' => 'decimal:2',
        'final_price_bdt'   => 'decimal:2',
        'advance_amount'    => 'decimal:2',
        'remaining_amount'  => 'decimal:2',
        'advance_paid'      => 'boolean',
        'remaining_paid'    => 'boolean',
        'scraped_data'      => 'array',
        'pricing_breakdown' => 'array',
        'product_features'  => 'array',
        'product_specs'     => 'array',
        'shipped_at'        => 'datetime',
        'delivered_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    /**
     * Generate a unique order number (e.g., FPO-20240115-00001)
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'FPO-' . now()->format('Ymd') . '-';
        $lastOrder = self::where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -5) + 1 : 1;
        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Relationship: Order belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Order has many transaction logs.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(IorTransactionLog::class, 'order_id');
    }

    /**
     * Accessor for weight in grams.
     */
    public function getProductWeightKgAttribute($value)
    {
        return $value ?: 0.500;
    }
}
