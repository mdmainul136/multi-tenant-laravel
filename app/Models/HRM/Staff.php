<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends TenantBaseModel
{
    protected $table = 'ec_staff';

    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'phone',
        'department_id',
        'designation',
        'role',
        'salary',
        'salary_type',
        'hire_date',
        'end_date',
        'status',
        'avatar',
        'address',
        'emergency_contact',
        'notes',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'hire_date' => 'date',
        'end_date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'staff_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'staff_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'staff_id');
    }
}
