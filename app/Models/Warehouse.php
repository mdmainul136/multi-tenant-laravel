<?php

namespace App\Models;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends TenantBaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
