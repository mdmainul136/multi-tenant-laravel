<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CODOrder extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id', 'user_id', 'amount', 'currency', 'status',
        'risk_score', 'otp_required', 'otp_code', 'otp_expires_at', 'otp_verified_at',
        'delivery_address', 'delivery_agent_id', 'collected_at',
        'failure_reason', 'notes',
    ];

    protected $casts = [
        'otp_required'    => 'boolean',
        'otp_expires_at'  => 'datetime',
        'otp_verified_at' => 'datetime',
        'collected_at'    => 'datetime',
    ];

    protected $hidden = ['otp_code'];

    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function isCollected(): bool  { return $this->status === 'payment_collected'; }
    public function isPending(): bool    { return $this->status === 'pending_payment'; }
    public function canShip(): bool      { return !$this->otp_required || $this->otp_verified_at !== null; }
}
