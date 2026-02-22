<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_suppliers';

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'website',
        'rating',
        'payment_terms',
        'lead_time_days',
        'currency',
        'status',
        'notes',
        'total_spend',
    ];

    protected $casts = [
        'rating' => 'integer',
        'lead_time_days' => 'integer',
        'total_spend' => 'decimal:2',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
