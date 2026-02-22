<?php

namespace App\Models\Notifications;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoreNotification extends TenantBaseModel
{
    protected $table = 'ec_notifications';

    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'icon',
        'color',
        'action_url',
        'notifiable_type',
        'notifiable_id',
        'is_broadcast',
        'read_at',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'json',
        'is_broadcast' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function markRead()
    {
        return $this->update(['read_at' => now()]);
    }
}
