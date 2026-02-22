<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordIorCourier extends Model
{
    protected $connection = 'mysql';
    protected $table = 'landlord_ior_couriers';

    protected $fillable = [
        'name', 'code', 'type', 'region_type', 
        'country_code', 'region_name', 
        'has_tracking', 'has_booking', 'is_active', 
        'api_docs_url', 'supported_services', 'description'
    ];

    protected $casts = [
        'supported_services' => 'json',
        'has_tracking'       => 'boolean',
        'has_booking'        => 'boolean',
        'is_active'          => 'boolean',
    ];

    /**
     * Scope for a specific country or region.
     */
    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('is_active', true)
            ->where(function($q) use ($countryCode) {
                $q->where('region_type', 'global')
                  ->orWhere('country_code', strtoupper($countryCode))
                  ->orWhere('region_name', 'Global');
            });
    }
}
