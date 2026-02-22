<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\LandlordIorCourier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class GlobalCourierService
{
    /**
     * Get available couriers for a specific country.
     * Searches for global, country-specific, and region-matched couriers.
     */
    public function getAvailableCouriers(string $countryCode, ?string $type = null): Collection
    {
        $query = LandlordIorCourier::forCountry($countryCode);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Suggest the best courier based on origin and destination.
     * Logic: 
     * - If origin != destination, prefer International couriers (DHL/FedEx).
     * - If destination is local to a specific supported country, include local options.
     */
    public function suggestCouriers(string $originCountry, string $destinationCountry): array
    {
        $isInternational = strtoupper($originCountry) !== strtoupper($destinationCountry);
        
        $suggestions = [
            'primary' => null,
            'alternatives' => [],
            'is_international' => $isInternational
        ];

        if ($isInternational) {
            $suggestions['primary'] = LandlordIorCourier::where('code', 'dhl')->first();
            $suggestions['alternatives'] = LandlordIorCourier::where('code', 'fedex')->get();
        } else {
            // Local suggestion for BD
            if (strtoupper($destinationCountry) === 'BD') {
                $suggestions['primary'] = LandlordIorCourier::where('code', 'pathao')->first();
                $suggestions['alternatives'] = LandlordIorCourier::whereIn('code', ['redx', 'steadfast'])->get();
            }
        }

        return $suggestions;
    }

    /**
     * Initialize/Seed core couriers into the Landlord DB.
     * (Normally done via a Seeder, but useful as a service method for auto-healing).
     */
    public function seedCoreCouriers(): void
    {
        $couriers = [
            [
                'name' => 'DHL Express', 
                'code' => 'dhl', 
                'type' => 'international', 
                'region_type' => 'global', 
                'has_booking' => true
            ],
            [
                'name' => 'FedEx Corporation', 
                'code' => 'fedex', 
                'type' => 'international', 
                'region_type' => 'global', 
                'has_booking' => true
            ],
            [
                'name' => 'Pathao Courier', 
                'code' => 'pathao', 
                'type' => 'domestic', 
                'region_type' => 'country', 
                'country_code' => 'BD', 
                'has_booking' => true
            ],
            [
                'name' => 'RedX Logistics', 
                'code' => 'redx', 
                'type' => 'domestic', 
                'region_type' => 'country', 
                'country_code' => 'BD', 
                'has_booking' => true
            ],
            [
                'name' => 'Steadfast Courier', 
                'code' => 'steadfast', 
                'type' => 'domestic', 
                'region_type' => 'country', 
                'country_code' => 'BD', 
                'has_booking' => true
            ],
        ];

        foreach ($couriers as $item) {
            LandlordIorCourier::updateOrCreate(['code' => $item['code']], $item);
        }
    }
}
