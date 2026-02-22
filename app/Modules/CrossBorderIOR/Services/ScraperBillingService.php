<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScraperBillingService
{
    /**
     * Default costs per successful scrape if not set in settings.
     */
    private const DEFAULT_COSTS = [
        'python'  => 0.0010, // Cost for server/bandwidth
        'oxylabs' => 0.0500, // External cost
        'apify'   => 0.1000, // Premium/Proxy cost
    ];

    /**
     * Log a scrape attempt and record the cost.
     */
    public function logScrape(string $provider, string $status, string $url, ?int $productId = null, ?array $responseSummary = null): void
    {
        $cost = 0;
        
        if ($status === 'success') {
            $cost = (float) IorSetting::get("scraper_cost_{$provider}", self::DEFAULT_COSTS[$provider] ?? 0);
        }

        try {
            DB::table('ior_scraper_logs')->insert([
                'provider'         => $provider,
                'marketplace'      => $this->detectMarketplace($url),
                'source_url'       => $url,
                'product_id'       => $productId,
                'status'           => $status,
                'cost'             => $cost,
                'response_summary' => $responseSummary ? json_encode($responseSummary) : null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Update tenant spend in settings (simplification: using first tenant or global for now)
            if ($cost > 0) {
                DB::table('ior_scraper_settings')->where('is_active', true)->increment('current_monthly_spend', $cost);
            }
        } catch (\Exception $e) {
            Log::error("[IOR Billing] Failed to log scrape: " . $e->getMessage());
        }
    }

    /**
     * Check if scraping is allowed based on budget.
     */
    public function canScrape(): bool
    {
        $settings = DB::table('ior_scraper_settings')->where('is_active', true)->first();
        if (!$settings) return true; // Default to true if no settings found

        if ($settings->current_monthly_spend >= $settings->monthly_budget_cap) {
            Log::warning("[IOR Billing] Scraper disabled: Budget cap reached ($" . $settings->monthly_budget_cap . ")");
            return false;
        }

        return true;
    }

    private function detectMarketplace(string $url): string
    {
        if (str_contains($url, 'amazon'))  return 'amazon';
        if (str_contains($url, 'walmart')) return 'walmart';
        return 'unknown';
    }
}



