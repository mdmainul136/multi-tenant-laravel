<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends TenantBaseModel
{
    protected $table = 'crm_deals';

    protected $fillable = [
        'title',
        'contact_id',
        'value',
        'currency',
        'stage',
        'probability',
        'expected_close_date',
        'actual_close_date',
        'assigned_to',
        'source',
        'description',
        'notes',
        'tags',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'probability'         => 'integer',
        'expected_close_date' => 'date',
        'actual_close_date'   => 'date',
        'tags'                => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'deal_id');
    }
}
