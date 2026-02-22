<?php

namespace App\Console\Commands\IOR;

use App\Modules\CrossBorderIOR\Services\ForeignProductSyncService;
use Illuminate\Console\Command;

/**
 * php artisan ior:sync-products
 *
 * Syncs live prices and availability for foreign catalogue products
 * from source marketplaces via Oxylabs API.
 * Scheduled daily in app/Console/Kernel.php.
 */
class SyncForeignProductsCommand extends Command
{
    protected $signature = 'ior:sync-products
                            {--ids= : Comma-separated catalog_products IDs to sync (default: all foreign)}
                            {--queue : Run sync in the background via SyncForeignProductJob}';

    protected $description = 'Sync live prices and availability for foreign (IOR) catalogue products via Oxylabs';

    public function handle(ForeignProductSyncService $service): int
    {
        $idsRaw     = $this->option('ids');
        $productIds = $idsRaw ? array_map('intval', explode(',', $idsRaw)) : null;

        if ($this->option('queue')) {
            $this->info('🚀 Dispatching sync jobs to the queue...');
            
            $query = \DB::table('ior_product_sources');
            if ($productIds) {
                $query->whereIn('product_id', $productIds);
            }

            $query->chunkById(100, function ($sources) {
                \App\Modules\CrossBorderIOR\Jobs\SyncForeignProductJob::dispatch(
                    $sources->pluck('product_id')->toArray()
                );
            }, 'id');

            $this->info('✅ Jobs dispatched successfully.');
            return self::SUCCESS;
        }

        $result = $service->sync($productIds);

        if (!$result['success']) {
            $this->error($result['message'] ?? 'Sync failed.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Total Products', (string) $result['total']);
        $this->components->twoColumnDetail('Synced',         "<fg=green>{$result['synced']}</>");
        $this->components->twoColumnDetail('Failed',         "<fg=red>{$result['failed']}</>");
        $this->newLine();

        foreach ($result['results'] as $r) {
            if ($r['success']) {
                $diff = isset($r['old_price'], $r['new_price'])
                    ? " \${$r['old_price']} → \${$r['new_price']}"
                    : '';
                $this->line("  <fg=green>✓</> #{$r['id']} {$r['name']}{$diff} | {$r['availability']}");
            } else {
                $this->line("  <fg=red>✗</> #{$r['id']}: {$r['error']}");
            }
        }

        $this->newLine();
        $this->info("✅ Done. {$result['synced']}/{$result['total']} products synced.");

        return self::SUCCESS;
    }
}
