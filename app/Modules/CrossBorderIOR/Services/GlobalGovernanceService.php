<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\LandlordIorRestrictedItem;
use App\Models\LandlordIorCountry;
use Illuminate\Support\Collection;

class GlobalGovernanceService
{
    /**
     * Get global restricted items, optionally filtered by origin country.
     */
    public function getGlobalRestrictedItems(?string $originCountry = null): Collection
    {
        return LandlordIorRestrictedItem::where('is_active', true)
            ->where(function($q) use ($originCountry) {
                $q->whereNull('origin_country_code')
                  ->orWhere('origin_country_code', $originCountry);
            })
            ->get();
    }

    /**
     * Get default logistics settings for a specific country.
     */
    public function getCountryDefaults(string $countryCode): ?LandlordIorCountry
    {
        return LandlordIorCountry::where('code', strtoupper($countryCode))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all supported origin countries.
     */
    public function getSupportedCountries(): Collection
    {
        return LandlordIorCountry::where('is_active', true)->get();
    }
}
