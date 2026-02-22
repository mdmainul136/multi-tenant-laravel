<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends TenantBaseModel
{
    protected $table = 'ec_departments';

    protected $fillable = [
        'name',
        'description',
        'manager_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'department_id');
    }
}
