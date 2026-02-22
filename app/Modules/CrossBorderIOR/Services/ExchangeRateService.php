<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CrossBorderIOR\IorSetting;

class ExchangeRateService
{
    private const CACHE_KEY = 'ior_usd_bdt_rate';
    private const CACHE_TTL = 3600; // 1 hour
    private const DEFAULT_RATE = 120.0;

    /**
     * Get current USD→BDT exchange rate.
     * Priority: Cache → Open Exchange Rates → Frankfurter → DB setting → default 120
     */
    public function getUsdToBdt(bool $forceRefresh = false): float
    {
        if (!$forceRefresh && Cache::has(self::CACHE_KEY)) {
            return (float) Cache::get(self::CACHE_KEY);
        }

        $rate = $this->fetchFromOpenExchangeRates()
            ?? $this->fetchFromFrankfurter()
            ?? (float) IorSetting::get('last_exchange_rate', self::DEFAULT_RATE);

        Cache::put(self::CACHE_KEY, $rate, self::CACHE_TTL);

        // Persist last known rate
        IorSetting::set('last_exchange_rate', (string) $rate, 'pricing');

        return $rate;
    }

    private function fetchFromOpenExchangeRates(): ?float
    {
        try {
            $response = Http::timeout(5)
                ->get('https://open.er-api.com/v6/latest/USD');

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['rates']['BDT'] ?? null;
                if ($rate) {
                    Log::info('[IOR] Exchange rate from Open ER API: ' . $rate);
                    return (float) $rate;
                }
            }
        } catch (\Exception $e) {
            Log::warning('[IOR] Open ER API failed: ' . $e->getMessage());
        }

        return null;
    }

    private function fetchFromFrankfurter(): ?float
    {
        try {
            $response = Http::timeout(5)
                ->get('https://api.frankfurter.app/latest?from=USD&to=BDT');

            if ($response->successful()) {
                $data  = $response->json();
                $rate  = $data['rates']['BDT'] ?? null;
                if ($rate) {
                    Log::info('[IOR] Exchange rate from Frankfurter: ' . $rate);
                    return (float) $rate;
                }
            }
        } catch (\Exception $e) {
            Log::warning('[IOR] Frankfurter API failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Clear cached rate (call after manual update).
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}



