<?php

namespace App\Modules\CrossBorderIOR\Jobs;

use App\Modules\CrossBorderIOR\Services\ForeignProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncForeignProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * 
     * @param array $productIds
     * @param string $provider
     */
    public function __construct(
        private array $productIds = [],
        private string $provider = 'oxylabs'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ForeignProductSyncService $syncService): void
    {
        // 1. Rate Limiting (Simple per-tenant throttle)
        // In a real SaaS, we'd use redis throttle. Here we use basic Cache/Sleep for demonstration.
        $tenantId = 1; // Default tenant
        $settings = \DB::table('ior_scraper_settings')->where('tenant_id', $tenantId)->first();
        $limit    = $settings->rate_limit_per_minute ?? 10;
        
        // Ensure we don't exceed the limit in this single job run
        // (Practical simplification: sleep between items if the batch is large)
        $delayMs = (60 / $limit) * 1000;

        Log::info("[IOR Queue] Starting sync for " . count($this->productIds) . " products using $this->provider (Limit: $limit/min)");

        $batchResults = [];
        foreach ($this->productIds as $id) {
            // Check budget before each item in the batch
            if (!app(\App\Modules\CrossBorderIOR\Services\ScraperBillingService::class)->canScrape()) {
                Log::warning("[IOR Queue] Batch stopped: Budget cap reached.");
                break;
            }

            $batchResults[] = $syncService->sync([$id], $this->provider);
            
            // Artificial delay to respect rate limit
            if (count($this->productIds) > 1) {
                usleep($delayMs * 1000);
            }
        }

        Log::info("[IOR Queue] Batch sync completed for " . count($batchResults) . " products.");
    }
}
