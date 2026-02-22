<?php

namespace App\Modules\CrossBorderIOR\Services;

class ShippingEngineService
{
    /**
     * Calculate estimated shipping cost based on dimensions and weight.
     * 
     * @param float $weightKg Actual weight in KG
     * @param float $lengthCm Length in CM
     * @param float $widthCm Width in CM
     * @param float $heightCm Height in CM
     * @param float $ratePerKg Shipping rate per KG (e.g. $8.0 for Air)
     * @return array
     */
    public function calculate(float $weightKg, float $lengthCm = 0, float $widthCm = 0, float $heightCm = 0, float $ratePerKg = 8.0): array
    {
        // Standard Volumetric Formula: (L * W * H) / 5000
        $volumetricWeight = ($lengthCm * $widthCm * $heightCm) / 5000;
        
        $chargeableWeight = max($weightKg, $volumetricWeight);
        
        // Ensure at least a minimum weight (e.g. 0.5kg)
        if ($chargeableWeight < 0.5 && $chargeableWeight > 0) {
             $chargeableWeight = 0.5;
        }

        $totalCostUsd = $chargeableWeight * $ratePerKg;

        return [
            'actual_weight'     => $weightKg,
            'volumetric_weight' => round($volumetricWeight, 2),
            'chargeable_weight' => round($chargeableWeight, 2),
            'rate_per_kg'       => $ratePerKg,
            'total_shipping_usd' => round($totalCostUsd, 2),
            'method'            => $chargeableWeight > $weightKg ? 'volumetric' : 'actual'
        ];
    }
}
