<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TenantAiSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider',
        'api_key',
        'model_name',
        'use_platform_credits',
        'training_notes',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'use_platform_credits' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }
}
