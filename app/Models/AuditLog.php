<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_type',
        'action',
        'payload',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the tenant associated with this log.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }
}
