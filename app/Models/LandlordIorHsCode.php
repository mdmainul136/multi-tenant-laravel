<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordIorHsCode extends Model
{
    protected $connection = 'mysql';
    protected $table = 'landlord_ior_hs_codes';

    protected $fillable = [
        'hs_code',
        'country_code',
        'category_en',
        'category_bn',
        'cd', 'rd', 'sd', 'vat', 'ait', 'at',
        'is_restricted',
        'restriction_reason'
    ];
}
