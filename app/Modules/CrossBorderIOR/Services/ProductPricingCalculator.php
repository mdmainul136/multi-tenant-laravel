<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorCustomsRate;
use App\Models\CrossBorderIOR\IorSetting;

class ProductPricingCalculator
{
    private ExchangeRateService $fxService;

    public function __construct(ExchangeRateService $fxService)
    {
        $this->fxService = $fxService;
    }

    /**
     * Calculate full BDT pricing for a foreign product.
     *
     * @param float  $usdPrice      — Source price in USD
     * @param float  $weightKg      — Product weight in KG (default 0.5 if unknown)
     * @param string $productTitle  — Used for customs category detection
     * @param string $shippingMethod — air | sea
     * @param float|null $customRate — Override customs %, null = auto-detect
     * @param float|null $marginRate — Override profit %, null = from settings
     * @return array
     */
    public function calculate(
        float $usdPrice,
        float $weightKg = 0.5,
        string $productTitle = '',
        string $shippingMethod = 'air',
        ?float $customRate = null,
        ?float $marginRate = null
    ): array {
        $dto = new \App\Modules\CrossBorderIOR\DTOs\IorPricingDTO(
            usdPrice: $usdPrice,
            weightKg: $weightKg,
            productTitle: $productTitle,
            shippingMethod: $shippingMethod,
            customRate: $customRate,
            marginRate: $marginRate
        );

        return app(\App\Modules\CrossBorderIOR\Actions\CalculateIorPricingAction::class)->execute($dto);
    }

    private function getShippingRate(string $method): float
    {
        return (float) \DB::table('ior_shipping_settings')
            ->where('shipping_method', $method)
            ->where('is_active', true)
            ->value('rate_per_kg') ?? ($method === 'sea' ? 400.0 : 1500.0);
    }

    private function getMinShippingCharge(string $method): float
    {
        return (float) \DB::table('ior_shipping_settings')
            ->where('shipping_method', $method)
            ->where('is_active', true)
            ->value('min_charge') ?? 500.0;
    }
}



