<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends TenantBaseModel
{
    protected $table = 'ec_attendance';

    protected $fillable = [
        'staff_id',
        'date',
        'check_in',
        'check_out',
        'hours_worked',
        'status',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'hours_worked' => 'decimal:2',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
