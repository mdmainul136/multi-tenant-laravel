<?php

namespace App\Models\WhatsApp;

use App\Models\TenantBaseModel;

class WhatsAppLog extends TenantBaseModel
{
    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'recipient',
        'message_type',
        'template_name',
        'message_body',
        'status',
        'external_id',
        'error',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
