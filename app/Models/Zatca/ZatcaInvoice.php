<?php

namespace App\Models\Zatca;

use App\Models\TenantBaseModel;
use App\Models\Ecommerce\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaInvoice extends TenantBaseModel
{
    protected $table = 'invoices'; // Using the augmented invoices table

    protected $fillable = [
        'order_id',
        'invoice_number',
        'invoice_date',
        'total_amount',
        'vat_amount',
        'zatca_qr',
        'xml_content',
        'compliance_status',
        'reporting_status',
        'response_payload',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'response_payload' => 'json',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
