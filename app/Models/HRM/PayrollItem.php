<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends TenantBaseModel
{
    protected $table = 'ec_payroll_items';

    protected $fillable = [
        'payroll_id',
        'title',
        'type',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
