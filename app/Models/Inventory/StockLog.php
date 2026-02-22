<?php

namespace App\Models\Inventory;

use App\Models\TenantBaseModel;
use App\Models\Ecommerce\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLog extends TenantBaseModel
{
    protected $table = 'ec_stock_logs';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'change',
        'balance_after',
        'type',
        'reference_type',
        'reference_id',
        'note',
        'user_id',
    ];

    protected $casts = [
        'change'        => 'integer',
        'balance_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
