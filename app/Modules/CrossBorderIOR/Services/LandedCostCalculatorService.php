<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\DB;

class LandedCostCalculatorService
{
    public function __construct(
        private CustomsDutyService $dutyService,
        private ShippingEngineService $shippingService,
        private GlobalGovernanceService $governance,
        private ComplianceSafetyService $compliance
    ) {}

    /**
     * Simulation of total landed cost from USD to BDT.
     */
    public function simulate(array $params, string $tenantId = null): array
    {
        $priceUsd    = (float) ($params['price_usd'] ?? 0);
        $weightKg    = (float) ($params['weight_kg'] ?? 0.5);
        $hsCode      = $params['hs_code'] ?? '8471.30.00'; 
        $dimensions  = $params['dimensions'] ?? ['l' => 0, 'w' => 0, 'h' => 0];
        $originCountry = $params['origin_country'] ?? null;
        
        // 0. Fetch Global Governance Defaults if available
        $countryDefaults = $originCountry ? $this->governance->getCountryDefaults($originCountry) : null;
        
        // 1. Calculate Shipping (using Global Default if tenant hasn't set one)
        $shippingRate = (float) ($params['shipping_rate'] ?? $countryDefaults?->default_shipping_rate_per_kg ?? 8.0);
        $shipping = $this->shippingService->calculate(
            $weightKg, 
            $dimensions['l'] ?? 0, 
            $dimensions['w'] ?? 0, 
            $dimensions['h'] ?? 0,
            $shippingRate
        );

        // 2. Calculate Duty (Assessable Value = Price + Insurance + Freight)
        $insurance = $priceUsd * 0.01;
        $duty = $this->dutyService->calculate($priceUsd, $hsCode, $shipping['total_shipping_usd'], $insurance, $tenantId);

        // 3. Compliance Check
        $compliance = $this->compliance->check($params['title'] ?? 'Generic Product', $params['description'] ?? '', $originCountry);

        // 4. Apply Exchange Rate with Safety Buffer
        $settings = DB::table('ior_scraper_settings')->first();
        $marketRate = (float) ($settings->base_exchange_rate ?? 120.0);
        $bufferPercent = (float) ($settings->exchange_buffer_percent ?? 2.0);
        
        $effectiveRate = $marketRate * (1 + ($bufferPercent / 100));
        
        $totalLandedUsd = $duty['landed_cost_usd'];
        $totalLandedBdt = $totalLandedUsd * $effectiveRate;

        return [
            'input' => [
                'price_usd' => $priceUsd,
                'hs_code'   => $hsCode,
                'weight'    => $weightKg,
                'origin_country' => $originCountry
            ],
            'shipping' => $shipping,
            'customs'  => $duty,
            'compliance' => $compliance,
            'currency' => [
                'market_rate'    => $marketRate,
                'buffer_percent' => $bufferPercent,
                'effective_rate' => round($effectiveRate, 2)
            ],
            'financials' => [
                'total_usd' => round($totalLandedUsd, 2),
                'total_bdt' => ceil($totalLandedBdt)
            ],
            'governance_applied' => !empty($countryDefaults)
        ];
    }
}
