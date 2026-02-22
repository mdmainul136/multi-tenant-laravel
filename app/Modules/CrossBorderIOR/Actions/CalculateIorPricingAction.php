<?php

namespace App\Modules\CrossBorderIOR\Actions;

use App\Models\CrossBorderIOR\IorCustomsRate;
use App\Models\CrossBorderIOR\IorSetting;
use App\Modules\CrossBorderIOR\Services\ExchangeRateService;
use App\Modules\CrossBorderIOR\DTOs\IorPricingDTO;
use Illuminate\Support\Facades\DB;

class CalculateIorPricingAction
{
    public function execute(IorPricingDTO $dto): array
    {
        $fxService = app(ExchangeRateService::class);
        $exchangeRate = $fxService->getUsdToBdt();

        // --- Base price
        $basePriceBdt = round($dto->usdPrice * $exchangeRate, 2);

        // --- Customs
        $customRate = $dto->customRate;
        if ($customRate === null) {
            $category    = IorCustomsRate::detectCategory($dto->productTitle);
            $customRate  = IorCustomsRate::getRateForCategory($category);
        } else {
            $category = 'custom';
        }
        $customsFee = round($basePriceBdt * ($customRate / 100), 2);

        // --- Shipping
        $shippingRatePerKg = $this->getShippingRate($dto->shippingMethod);
        $shippingCost = max(
            round($dto->weightKg * $shippingRatePerKg, 2),
            $this->getMinShippingCharge($dto->shippingMethod)
        );

        // --- Warehouse / Handling
        $warehouseCost = (float) IorSetting::get('warehouse_handling_fee', 150);

        // --- Profit margin
        $marginRate = $dto->marginRate;
        if ($marginRate === null) {
            $marginRate = (float) IorSetting::get('default_profit_margin', 20);
        }
        $subtotal       = $basePriceBdt + $customsFee + $shippingCost + $warehouseCost;
        $profitMargin   = round($subtotal * ($marginRate / 100), 2);

        // --- Final price
        $finalPrice = (int) ceil($subtotal + $profitMargin);

        // --- Advance Payment Calculation
        $advancePercent = (float) IorSetting::get('advance_payment_percent', 50);

        if ($finalPrice > 100000) {
            $advancePercent = 100;
        } elseif ($finalPrice > 50000) {
            $advancePercent = max($advancePercent, 70);
        } elseif ($finalPrice > 20000) {
            $advancePercent = max($advancePercent, 60);
        }
        
        $advancePercent = max(0, min(100, $advancePercent));
        $advanceAmount  = round($finalPrice * ($advancePercent / 100), 2);
        $remainingAmount = ($advancePercent >= 100) ? 0 : round($finalPrice - $advanceAmount, 2);

        return [
            'exchange_rate'       => $exchangeRate,
            'usd_price'           => $dto->usdPrice,
            'base_price_bdt'      => $basePriceBdt,
            'customs_category'    => $category,
            'customs_rate_pct'    => $customRate,
            'customs_fee_bdt'     => $customsFee,
            'shipping_method'     => $dto->shippingMethod,
            'shipping_rate_per_kg'=> $shippingRatePerKg,
            'weight_kg'           => $dto->weightKg,
            'shipping_cost_bdt'   => $shippingCost,
            'warehouse_cost_bdt'  => $warehouseCost,
            'profit_margin_pct'   => $marginRate,
            'profit_margin_bdt'   => $profitMargin,
            'estimated_price_bdt' => (float) $finalPrice,
            'advance_percent'     => $advancePercent,
            'advance_amount'      => $advanceAmount,
            'remaining_amount'    => $remainingAmount,
        ];
    }

    private function getShippingRate(string $method): float
    {
        return (float) DB::table('ior_shipping_settings')
            ->where('shipping_method', $method)
            ->where('is_active', true)
            ->value('rate_per_kg') ?? ($method === 'sea' ? 400.0 : 1500.0);
    }

    private function getMinShippingCharge(string $method): float
    {
        return (float) DB::table('ior_shipping_settings')
            ->where('shipping_method', $method)
            ->where('is_active', true)
            ->value('min_charge') ?? 500.0;
    }
}



