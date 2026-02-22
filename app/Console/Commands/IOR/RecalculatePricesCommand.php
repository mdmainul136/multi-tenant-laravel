<?php

namespace App\Console\Commands\IOR;

use App\Modules\CrossBorderIOR\Services\BulkPriceRecalculatorService;
use Illuminate\Console\Command;

/**
 * php artisan ior:recalculate-prices
 *
 * Re-prices all pending IOR orders with the latest USD→BDT exchange rate.
 * Scheduled daily in app/Console/Kernel.php.
 */
class RecalculatePricesCommand extends Command
{
    protected $signature = 'ior:recalculate-prices
                            {--force : Force recalculation even if rate has not changed significantly}
                            {--ids=  : Comma-separated order IDs to recalculate (default: all pending)}';

    protected $description = 'Bulk recalculate BDT prices for pending IOR orders based on the latest exchange rate';

    public function handle(BulkPriceRecalculatorService $service): int
    {
        $force    = (bool) $this->option('force');
        $idsRaw   = $this->option('ids');
        $orderIds = $idsRaw ? array_map('intval', explode(',', $idsRaw)) : null;

        $this->info("🔄 IOR Bulk Price Recalculation" . ($force ? ' (forced)' : ''));

        $result = $service->recalculate(
            orderIds:    $orderIds,
            force:       $force,
            triggeredBy: 'artisan',
        );

        $this->newLine();
        $this->components->twoColumnDetail('Exchange Rate (USD→BDT)', "{$result['previous_rate']} → <fg=green;options=bold>{$result['current_rate']}</>");
        $this->components->twoColumnDetail('Rate Change', round($result['rate_change_pct'], 3) . '%');
        $this->components->twoColumnDetail('Total Orders Checked', (string) $result['total_orders']);
        $this->components->twoColumnDetail('Updated', "<fg=green>{$result['updated']}</>");
        $this->components->twoColumnDetail('Skipped', "<fg=yellow>{$result['skipped']}</>");
        $this->newLine();

        foreach ($result['price_changes'] as $change) {
            $this->line("  Order #{$change['order_number']}: ৳{$change['old_price_bdt']} → ৳{$change['new_price_bdt']} ({$change['change_pct']}%)");
        }

        if ($result['updated'] === 0 && $result['skipped'] > 0) {
            $this->warn('No prices updated — rate change below 1% threshold. Use --force to override.');
        } else {
            $this->info("✅ Done. {$result['updated']} orders updated.");
        }

        return self::SUCCESS;
    }
}
