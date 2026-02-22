<?php

namespace App\Models\Ecommerce;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationTemplate extends TenantBaseModel
{
    use HasFactory;

    protected $table = 'ec_notification_templates';

    protected $fillable = [
        'name',
        'type', // email, sms, whatsapp
        'subject',
        'content',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];
}
