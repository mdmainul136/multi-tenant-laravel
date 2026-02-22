<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordIorCountry extends Model
{
    protected $connection = 'mysql';
    protected $table = 'landlord_ior_countries';

    protected $fillable = [
        'name',
        'code',
        'flag_url',
        'default_currency_code',
        'default_duty_percent',
        'default_shipping_rate_per_kg',
        'is_active',
    ];
}
