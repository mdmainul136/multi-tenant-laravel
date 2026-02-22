<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends TenantBaseModel
{
    protected $table = 'crm_activities';

    protected $fillable = [
        'type',
        'subject',
        'description',
        'contact_id',
        'deal_id',
        'assigned_to',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'duration',
        'outcome',
        'created_by',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'completed_at' => 'datetime',
        'duration'     => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
