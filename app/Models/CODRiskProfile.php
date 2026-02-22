<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CODRiskProfile extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'user_id', 'return_rate', 'cancellation_count',
        'is_blacklisted', 'risk_score', 'blacklist_reason',
    ];

    protected $casts = [
        'return_rate'     => 'float',
        'is_blacklisted'  => 'boolean',
    ];
}
