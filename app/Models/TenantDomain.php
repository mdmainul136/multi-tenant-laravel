<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantDomain extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'is_verified',
        'verification_token',
        'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }
}
