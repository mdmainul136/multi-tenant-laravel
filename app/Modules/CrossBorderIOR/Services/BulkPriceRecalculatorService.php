<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkPriceRecalculatorService
 *
 * Recalculates BDT prices on all (or selected) pending IOR orders when
 * the USD→BDT exchange rate changes significantly.
 *
 * Ported from Supabase `bulk-recalculate-prices`.
 *
 * In the IOR context "products" = pending IorForeignOrder rows that
 * haven't been confirmed yet (status: pending | sourcing).
 * Each order stores `source_price_usd` and we recalculate via
 * ProductPricingCalculator so all fee rules stay consistent.
 */
class BulkPriceRecalculatorService
{
    private const SKIP_RATE_THRESHOLD = 0.01; // 1 % change

    public function __construct(
        private ExchangeRateService             $fx,
        private ProductPricingCalculator        $pricer,
        private ExchangeRateNotificationService $notifier,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // MAIN
    // ──────────────────────────────────────────────────────────────

    /**
     * Recalculate prices for pending/sourcing IOR orders.
     *
     * @param  array|null $orderIds  Specific IDs, or null/[] for all pending orders
     * @param  bool       $force     If true skip the 1% threshold check
     * @param  string     $triggeredBy  'manual' | 'cron' | etc.
     * @return array  Summary
     */
    public function recalculate(
        ?array $orderIds = null,
        bool   $force    = false,
        string $triggeredBy = 'manual'
    ): array {
        $currentRate  = $this->fx->fetchLiveRate();
        $previousRate = $this->getLastRecordedRate();

        $rateChangePct = $previousRate > 0
            ? (($currentRate - $previousRate) / $previousRate) * 100
            : 0;

        Log::info("[IOR BulkRecalc] rate={$currentRate} prev={$previousRate} change={$rateChangePct}%");

        // Query: only re-price orders that haven't been confirmed yet
        $query = IorForeignOrder::whereIn('order_status', [
            IorForeignOrder::STATUS_PENDING,
            IorForeignOrder::STATUS_SOURCING,
        ])->whereNotNull('source_price_usd')
          ->where('source_price_usd', '>', 0);

        if (!empty($orderIds)) {
            $query->whereIn('id', $orderIds);
        }

        $orders = $query->get();

        $updated  = 0;
        $skipped  = 0;
        $changes  = [];

        foreach ($orders as $order) {
            $oldRate = (float) ($order->exchange_rate ?? $previousRate);

            // Skip if rate hasn't changed much (unless forced)
            if (!$force && $oldRate > 0 && abs($currentRate - $oldRate) / $oldRate < self::SKIP_RATE_THRESHOLD) {
                $skipped++;
                continue;
            }

            $oldTotal = $order->estimated_price_bdt;

            // Recalculate via the same service that originally priced this order
            $pricing = $this->pricer->calculate(
                usdPrice      : (float) $order->source_price_usd * (int) max(1, $order->quantity),
                weightKg      : $order->product_weight_kg,
                productTitle  : $order->product_name,
                shippingMethod: $order->scraped_data['shipping_method'] ?? 'air',
            );

            $order->update([
                'exchange_rate'       => $currentRate,
                'base_price_bdt'      => $pricing['base_price_bdt'],
                'customs_fee_bdt'     => $pricing['customs_fee_bdt'],
                'shipping_cost_bdt'   => $pricing['shipping_cost_bdt'],
                'profit_margin_bdt'   => $pricing['profit_margin_bdt'],
                'estimated_price_bdt' => $pricing['estimated_price_bdt'],
                'advance_amount'      => $pricing['advance_amount'],
                'remaining_amount'    => $pricing['remaining_amount'],
                'pricing_breakdown'   => $pricing,
            ]);

            // Log the change
            $this->logPriceChange($order->id, $oldTotal, $pricing['estimated_price_bdt'], $oldRate, $currentRate, $triggeredBy);

            $changePct = $oldTotal > 0
                ? (($pricing['estimated_price_bdt'] - $oldTotal) / $oldTotal) * 100
                : 0;

            $changes[] = [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'old_price_bdt' => $oldTotal,
                'new_price_bdt' => $pricing['estimated_price_bdt'],
                'old_rate'      => $oldRate,
                'new_rate'      => $currentRate,
                'change_pct'    => round($changePct, 2),
            ];

            $updated++;
            Log::info("[IOR BulkRecalc] Order {$order->order_number}: ৳{$oldTotal} → ৳{$pricing['estimated_price_bdt']}");
        }

        // Record current rate
        $this->recordExchangeRate($currentRate, $updated);

        // Send admin email notification
        try {
            $this->notifier->notify(
                newRate:       $currentRate,
                previousRate:  $previousRate,
                ordersUpdated: $updated,
                totalOrders:   $orders->count(),
                errors:        [],
                triggeredBy:   $triggeredBy,
            );
        } catch (\Exception $e) {
            Log::warning('[IOR BulkRecalc] Notification failed: ' . $e->getMessage());
        }

        return [
            'success'              => true,
            'current_rate'         => $currentRate,
            'previous_rate'        => $previousRate,
            'rate_change_pct'      => round($rateChangePct, 4),
            'total_orders'         => $orders->count(),
            'updated'              => $updated,
            'skipped'              => $skipped,
            'forced'               => $force,
            'price_changes'        => $changes,
            'triggered_by'         => $triggeredBy,
            'timestamp'            => now()->toIso8601String(),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Exchange Rate Helpers
    // ──────────────────────────────────────────────────────────────

    private function getLastRecordedRate(): float
    {
        $row = \DB::table('ior_exchange_rate_logs')
            ->orderByDesc('created_at')
            ->value('rate');

        return $row ? (float) $row : $this->fx->getUsdToBdt();
    }

    private function recordExchangeRate(float $rate, int $ordersUpdated): void
    {
        try {
            \DB::table('ior_exchange_rate_logs')->insert([
                'rate'             => $rate,
                'source'           => 'bulk_recalculate',
                'orders_updated'   => $ordersUpdated,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('[IOR BulkRecalc] Could not log exchange rate: ' . $e->getMessage());
        }
    }

    private function logPriceChange(int $orderId, float $oldPrice, float $newPrice, float $oldRate, float $newRate, string $trigger): void
    {
        try {
            \DB::table('ior_logs')->insert([
                'order_id'    => $orderId,
                'event'       => 'price_recalculated',
                'payload'     => json_encode([
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'old_rate'  => $oldRate,
                    'new_rate'  => $newRate,
                    'trigger'   => $trigger,
                ]),
                'status'      => 'ok',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('[IOR BulkRecalc] Could not log price change: ' . $e->getMessage());
        }
    }
}



