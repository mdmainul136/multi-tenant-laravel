<?php

namespace App\Models\Notifications;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends TenantBaseModel
{
    protected $table = 'ec_notification_templates';

    protected $fillable = [
        'key',
        'name',
        'title_template',
        'body_template',
        'channel',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
