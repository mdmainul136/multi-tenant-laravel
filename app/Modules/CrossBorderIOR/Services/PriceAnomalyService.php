<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Log;

class PriceAnomalyService
{
    private const DEFAULT_THRESHOLD = 0.20; // 20% change

    /**
     * Detect if a price change is an anomaly.
     * 
     * @return array [is_anomaly, percentage_change, direction]
     */
    public function detect(float $oldPrice, float $newPrice, ?float $threshold = null): array
    {
        if ($oldPrice <= 0) {
            return ['is_anomaly' => false, 'percentage' => 0, 'direction' => 'none'];
        }

        $threshold = $threshold ?? (float) config('ior.price_anomaly_threshold', self::DEFAULT_THRESHOLD);
        
        $diff = $newPrice - $oldPrice;
        $percentage = abs($diff / $oldPrice);

        $isAnomaly = $percentage > $threshold;

        if ($isAnomaly) {
            Log::warning("[IOR Anomaly] Price change detected: $oldPrice -> $newPrice (" . round($percentage * 100, 2) . "%)");
        }

        return [
            'is_anomaly' => $isAnomaly,
            'percentage' => $percentage,
            'direction'  => $diff > 0 ? 'up' : 'down',
        ];
    }
}
