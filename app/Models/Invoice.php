<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'module_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subscription_type',
        'subtotal',
        'tax',
        'discount',
        'total',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber()
    {
        $yearMonth = now()->format('Ym');
        $prefix = "INV-{$yearMonth}-";
        
        $lastInvoice = self::where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid()
    {
        $this->update(['status' => 'paid']);
    }

    /**
     * Mark invoice as cancelled
     */
    public function markAsCancelled()
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue()
    {
        return $this->status === 'pending' && 
               $this->due_date && 
               $this->due_date->isPast();
    }

    /**
     * Get formatted invoice number
     */
    public function getFormattedNumberAttribute()
    {
        return $this->invoice_number;
    }

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
