<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainOrder extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'tenant_domain_id',
        'domain',
        'amount',
        'currency',
        'payment_id',
        'status',
        'registration_years',
        'expiry_date',
        'registrar_data',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
        'registrar_data' => 'array',
    ];


    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'stripe_session_id');
    }

    public function tenantDomain()
    {
        return $this->belongsTo(TenantDomain::class, 'tenant_domain_id');
    }

    public function invoice()
    {
        // Link through the payment record
        return $this->hasOneThrough(
            Invoice::class,
            Payment::class,
            'stripe_session_id', // Foreign key on Payment (linking to DomainOrder.payment_id)
            'payment_id',        // Foreign key on Invoice (linking to Payment.id)
            'payment_id',        // Local key on DomainOrder
            'id'                 // Local key on Payment
        );
    }
}
