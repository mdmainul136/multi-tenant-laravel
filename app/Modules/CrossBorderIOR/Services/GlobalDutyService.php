<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\LandlordIorApiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GlobalDutyService
{
    /**
     * Fetch HS Code and Duty rates for a specific country from global APIs.
     */
    public function lookup(string $hsCode, string $destinationCountry, string $provider = 'zonos'): ?array
    {
        $config = LandlordIorApiConfig::where('is_active', true)
            ->where('provider', $provider)
            ->first();

        if (!$config) {
            Log::error("Global Duty lookup failed: No active API configuration for {$provider}.");
            return null;
        }

        try {
            // Real API calls would go here using $config->api_key
            Log::info("Calling Global API ({$provider}) for HS Code {$hsCode} to {$destinationCountry}");
            
            return [
                'hs_code' => $hsCode,
                'country' => $destinationCountry,
                'cd' => 12.5, 'rd' => 0.0, 'sd' => 0.0, 'vat' => 15.0, 'ait' => 5.0, 'at' => 5.0,
                'provider' => $provider,
                'cost_usd' => (float) $config->cost_per_lookup
            ];
        } catch (\Exception $e) {
            Log::error("Global API lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Infer HS Code using 3rd party specialized classification APIs (e.g. Hurricane).
     */
    public function classify(string $title, string $description, string $country = 'BD'): ?array
    {
        $config = LandlordIorApiConfig::where('is_active', true)
            ->where('provider', 'hurricane')
            ->first();

        if (!$config) return null;

        Log::info("Calling 3rd Party Classifier (Hurricane) for: {$title}");

        // Mocking specialized classification response
        return [
            [
                'hs_code' => '8471.30.00',
                'description' => 'Laptop Computers',
                'confidence' => 0.98,
                'provider' => 'hurricane'
            ]
        ];
    }
}
