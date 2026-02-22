<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends TenantBaseModel
{
    protected $table = 'ec_payrolls';

    protected $fillable = [
        'staff_id',
        'month',
        'basic_salary',
        'total_allowance',
        'total_deduction',
        'net_salary',
        'status',
        'payment_date',
        'payment_method',
        'note',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'total_allowance' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
