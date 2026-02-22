<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'mysql'; // Master database

    protected $fillable = [
        'tenant_id',
        'module_id',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'transaction_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'payment_gateway_response',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the payment
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the module for this payment
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(string $transactionId = null): void
    {
        $this->update([
            'payment_status' => 'completed',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'payment_status' => 'failed',
        ]);
    }
}
