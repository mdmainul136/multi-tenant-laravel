<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends TenantBaseModel
{
    protected $table = 'crm_contacts';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'company',
        'job_title',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'website',
        'linkedin',
        'twitter',
        'source',
        'status',
        'assigned_to',
        'notes',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'contact_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
