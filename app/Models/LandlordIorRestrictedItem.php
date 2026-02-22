<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordIorRestrictedItem extends Model
{
    protected $connection = 'mysql'; // Explicitly use master connection
    protected $table = 'landlord_ior_restricted_items';

    protected $fillable = [
        'keyword',
        'reason',
        'severity',
        'origin_country_code',
        'is_active',
    ];
}
