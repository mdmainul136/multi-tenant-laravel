<?php

namespace App\Models\Marketplace;

use App\Models\TenantBaseModel;
use App\Models\User;
use App\Models\Ecommerce\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends TenantBaseModel
{
    protected $table = 'ec_vendors';

    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'description',
        'commission_rate',
        'status',
        'balance',
        'settings',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'balance' => 'decimal:2',
        'settings' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }
}
