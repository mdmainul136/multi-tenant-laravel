<?php

namespace App\Models\POS;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends TenantBaseModel
{
    protected $table = 'pos_sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }
}
