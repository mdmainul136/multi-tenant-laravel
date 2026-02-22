<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Modules\CrossBorderIOR\Actions\CalculateIorPricingAction;
use App\Modules\CrossBorderIOR\DTOs\IorPricingDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseMarginCalculator
{
    public function __construct(
        private CalculateIorPricingAction $calculator
    ) {}

    /**
     * Bulk recalculate local BDT prices for foreign products based on their current USD cost.
     */
    public function recalculateAll(?array $productIds = null): array
    {
        $query = DB::table('ec_products')
            ->where('product_type', 'foreign')
            ->whereNull('deleted_at');

        if ($productIds) {
            $query->whereIn('id', $productIds);
        }

        $products = $query->get();
        $updated = 0;

        foreach ($products as $product) {
            $usdPrice = (float) $product->cost;
            if ($usdPrice <= 0) continue;

            $result = $this->calculator->execute(new IorPricingDTO(
                usdPrice: $usdPrice,
                productTitle: $product->name,
                weightKg: (float) ($product->weight ?? 0.5)
            ));

            $newPrice = $result['estimated_price_bdt'];

            DB::table('ec_products')->where('id', $product->id)->update([
                'price' => $newPrice,
                'updated_at' => now(),
            ]);

            $updated++;
        }

        return ['total' => $products->count(), 'updated' => $updated];
    }
}
