<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends TenantBaseModel
{
    protected $table = 'ec_leave_requests';

    protected $fillable = [
        'staff_id',
        'type',
        'from_date',
        'to_date',
        'days',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'admin_note',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
