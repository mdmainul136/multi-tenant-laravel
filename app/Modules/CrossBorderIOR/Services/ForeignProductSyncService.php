<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\Ecommerce\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ForeignProductSyncService
 *
 * Re-fetches live prices and availability for foreign (IOR) catalogue products
 * from their source marketplaces via Oxylabs real-time scraping API.
 *
 * Ported from Supabase `sync-foreign-products`.
 *
 * Syncs `catalog_products` rows where `product_type = 'foreign'`.
 * Updates: price (USD), availability, attributes.last_synced_at
 */
class ForeignProductSyncService
{
    private const OXYLABS_URL = 'https://realtime.oxylabs.io/v1/queries';

    public function __construct(
        private ApifyScraperService  $apify,
        private PythonScraperService $python,
        private ScraperBillingService $billing,
        private PriceAnomalyService   $anomaly,
        private RestockAlertService   $alerts,
        private IorScraperHealthService $health,
        private WebhookDispatcherService $webhooks
    ) {}

    /** Oxylabs source + parse settings per marketplace */
    private const MARKETPLACE_CONFIGS = [
        'amazon' => [
            'source'          => 'amazon',
            'render'          => 'html',
            'parse'           => true,
            'geo_location'    => '90210',
            'locale'          => 'en-us',
            'user_agent_type' => 'desktop',
        ],
        'ebay' => [
            'source'       => 'universal_ecommerce',
            'render'       => 'html',
            'parse'        => true,
            'geo_location' => '10001',
        ],
        'walmart' => [
            'source'       => 'universal_ecommerce',
            'render'       => 'html',
            'parse'        => true,
            'geo_location' => '10001',
        ],
        'alibaba' => [
            'source' => 'universal_ecommerce',
            'render' => 'html',
            'parse'  => true,
        ],
        'aliexpress' => [
            'source' => 'universal_ecommerce',
            'render' => 'html',
            'parse'  => true,
        ],
        'target' => [
            'source' => 'universal_ecommerce',
            'render' => 'html',
            'parse'  => true,
        ],
        'bestbuy' => [
            'source' => 'universal_ecommerce',
            'render' => 'html',
            'parse'  => true,
        ],
    ];

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Sync foreign products.
     *
     * @param  array|null $productIds  Specific product IDs to sync, or null for all
     * @param  string $provider 'oxylabs' or 'apify'
     * @return array  Summary with per-product results
     */
    public function sync(?array $productIds = null, string $provider = 'oxylabs'): array
    {
        $username = config('services.oxylabs.username') ?: env('OXYLABS_USERNAME');
        $password = config('services.oxylabs.password') ?: env('OXYLABS_PASSWORD');

        if ($provider === 'oxylabs' && (!$username || !$password)) {
            return [
                'success' => false,
                'message' => 'Oxylabs scraping service not configured. Set OXYLABS_USERNAME and OXYLABS_PASSWORD in .env.',
            ];
        }

        // Fetch sources to sync
        $query = \DB::table('ior_product_sources as s')
            ->join('ec_products as p', 's.product_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->select('s.*', 'p.name', 'p.cost', 'p.is_active');

        if (!empty($productIds)) {
            $query->whereIn('s.product_id', $productIds);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            return ['success' => true, 'message' => 'No foreign product sources to sync.', 'synced' => 0, 'failed' => 0];
        }

        $authHeader = 'Basic ' . base64_encode("{$username}:{$password}");
        $results    = [];

        foreach ($sources as $source) {
            $startTime = microtime(true);
            $originalUrl = $source->source_url;
            $marketplace = $source->marketplace ?? 'amazon';

            // 1. Budget Guard
            if (!$this->billing->canScrape()) {
                $results[] = [
                    'id'      => $source->product_id,
                    'success' => false,
                    'error'   => 'Budget cap reached'
                ];
                continue;
            }

            try {
                if ($provider === 'apify') {
                    $scraped = $this->apify->scrapeProduct($originalUrl);
                    $syncData = [
                        'price'        => $scraped['price_usd'],
                        'availability' => $scraped['availability'],
                        'hash'         => md5(json_encode($scraped)),
                    ];
                } elseif ($provider === 'python') {
                    $tenantId = \DB::table('ior_scraper_settings')->value('tenant_id');
                    $scraped = $this->python->scrapeProduct($originalUrl, $tenantId);
                    $syncData = [
                        'price'        => $scraped['price_usd'],
                        'availability' => $scraped['availability'],
                        'hash'         => md5(json_encode($scraped)),
                        'variants'     => $scraped['variants'] ?? [],
                    ];
                } else {
                    $config = self::MARKETPLACE_CONFIGS[$marketplace] ?? self::MARKETPLACE_CONFIGS['amazon'];
                    $payload = array_merge(['url' => $originalUrl], $config);

                    Log::info("[IOR Sync] Scraping product {$source->product_id}: {$originalUrl} (via Oxylabs)");

                    $response = Http::withHeaders([
                        'Authorization' => $authHeader,
                        'Content-Type'  => 'application/json',
                    ])->timeout(60)->post(self::OXYLABS_URL, $payload);

                    if ($response->failed()) {
                        $results[] = [
                            'id'      => $source->product_id,
                            'success' => false,
                            'error'   => 'Oxylabs API error: ' . $response->status()
                        ];
                        continue;
                    }

                    $rawData  = $response->json();
                    $syncData = $this->extractSyncData($rawData, $marketplace);
                    $syncData['hash'] = md5(json_encode($rawData));
                }

                if (!$syncData) {
                    $this->billing->logScrape($provider, 'failed', $originalUrl, $source->product_id, ['error' => 'Parsing failed']);
                    $results[] = [
                        'id'      => $source->product_id,
                        'success' => false,
                        'error'   => 'Could not parse scrape response'
                    ];
                    continue;
                }

                // Billing: Log success
                $this->billing->logScrape($provider, 'success', $originalUrl, $source->product_id);

                $oldPrice  = (float) $source->last_usd_price;
                $inStock   = $syncData['availability'] === 'in_stock';
                $wasOos    = $source->stock_status === 'out_of_stock';
                $dataChanged = $source->checksum_hash !== $syncData['hash'];

                // --- CHANGE DETECTION EVENTS ---
                if ($dataChanged) {
                    Log::info("[IOR Event] Data change detected for Product {$source->product_id}");
                    // Here we could trigger a Laravel Event: ProductDataChanged::dispatch($source)
                }

                if ($syncData['availability'] === 'removed') {
                    Log::warning("[IOR Event] Product REMOVED from source: {$originalUrl}");
                    \DB::table('ec_products')->where('id', $source->product_id)->update(['is_active' => false]);
                    
                    $this->webhooks->dispatch($tenantId, 'product_removed', [
                        'product_id' => $source->product_id,
                        'source_url' => $originalUrl
                    ]);
                }

                // --- ANOMALY DETECTION ---
                $anomalyResult = $this->anomaly->detect($oldPrice, (float)($syncData['price'] ?? 0));
                
                // --- RESTOCK ALERT ---
                if ($wasOos && $inStock && ($source->restock_alert_enabled ?? false)) {
                    $this->alerts->handleRestock($source->product_id, $originalUrl);
                    
                    $this->webhooks->dispatch($tenantId, 'product_restocked', [
                        'product_id' => $source->product_id,
                        'price_usd'  => $syncData['price'],
                        'source_url' => $originalUrl
                    ]);
                }

                // --- AUTO-STOCK Logic ---
                $updateProduct = [
                    'is_active'  => $inStock ? $source->is_active : false,
                    'updated_at' => now(),
                ];

                if ($syncData['price'] !== null) {
                    $updateProduct['cost'] = $syncData['price'];
                }

                \DB::table('ec_products')->where('id', $source->product_id)->update($updateProduct);

                // Update Primary Source Tracking
                \DB::table('ior_product_sources')->where('id', $source->id)->update([
                    'stock_status'    => $syncData['availability'],
                    'last_usd_price'  => $syncData['price'],
                    'last_checked_at' => now(),
                    'last_restocked_at' => ($wasOos && $inStock) ? now() : $source->last_restocked_at,
                    'checksum_hash'   => $syncData['hash'],
                    'updated_at'      => now(),
                ]);

                // --- COMPETITOR SYNC (Lowest Source Win) ---
                $bestPrice = $syncData['price'] ?? 999999.0;
                $competitors = \DB::table('ior_competitor_sources')->where('product_id', $source->product_id)->get();
                
                foreach ($competitors as $comp) {
                    try {
                        // Use Python to sync competitors (faster/internal)
                        $cScraped = $this->python->scrapeProduct($comp->source_url, $tenantId);
                        $cPrice = (float) ($cScraped['price_usd'] ?? 0);
                        $cStock = $cScraped['availability'] ?? 'unknown';

                        \DB::table('ior_competitor_sources')->where('id', $comp->id)->update([
                            'last_usd_price' => $cPrice,
                            'stock_status'   => $cStock,
                            'last_checked_at' => now(),
                        ]);

                        if ($cStock === 'in_stock' && $cPrice > 0 && $cPrice < $bestPrice) {
                            $bestPrice = $cPrice;
                        }
                    } catch (\Exception $e) {
                        Log::error("[IOR CompetitorSync] Failed for {$comp->source_url}: " . $e->getMessage());
                    }
                }

                // If a competitor is cheaper, update the master product cost
                if ($bestPrice < ($syncData['price'] ?? 999999.0)) {
                    Log::info("[IOR DynamicPricing] Competitor price found lower: \${$bestPrice} for Product {$source->product_id}");
                    \DB::table('ec_products')->where('id', $source->product_id)->update(['cost' => $bestPrice]);

                    $this->webhooks->dispatch($tenantId, 'price_change', [
                        'product_id' => $source->product_id,
                        'old_price'  => $syncData['price'],
                        'new_price'  => $bestPrice,
                        'marketplace'=> 'competitor_lowest'
                    ]);
                }

                // Record Price History (Best Price observed)
                \DB::table('ior_price_history')->insert([
                    'product_id'   => $source->product_id,
                    'price_usd'    => $bestPrice < 999999.0 ? $bestPrice : ($syncData['price'] ?? $oldPrice),
                    'stock_status' => $syncData['availability'],
                    'recorded_at'  => now(),
                ]);

                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $this->health->recordSignal($marketplace, 'success', $duration);

                // --- VARIANT SYNC ---
                if (!empty($syncData['variants'])) {
                    foreach ($syncData['variants'] as $v) {
                        $type = $v['type'] ?? 'option';
                        foreach ($v['values'] as $val) {
                            $name = is_array($val) ? ($val['name'] ?? 'Unknown') : $val;
                            
                            \DB::table('ior_product_variants')
                                ->where('product_id', $source->product_id)
                                ->where('variant_key', md5($type . $name))
                                ->update([
                                    'stock_status' => $syncData['availability'], // Simplified share status
                                    'updated_at'   => now(),
                                ]);
                        }
                    }
                }

                Log::info("[IOR Sync] Product {$source->product_id}: \${$oldPrice} â†’ \${$syncData['price']} | {$syncData['availability']} | Anomaly: " . ($anomalyResult['is_anomaly'] ? 'YES' : 'NO'));

                $results[] = [
                    'id'           => $source->product_id,
                    'name'         => $source->name,
                    'success'      => true,
                    'old_price'    => $oldPrice,
                    'new_price'    => $syncData['price'],
                    'availability' => $syncData['availability'],
                ];
            } catch (\Exception $e) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $this->health->recordSignal($marketplace, 'failed', $duration, $e->getMessage());

                Log::error("[IOR Sync] Product " . ($source->product_id ?? 'unknown') . " exception: " . $e->getMessage());
                $results[] = [
                    'id'      => $source->product_id ?? null,
                    'success' => false,
                    'error'   => $e->getMessage()
                ];
            }
        }

        $succeeded = count(array_filter($results, fn($r) => $r['success']));
        $failed    = count($results) - $succeeded;

        return [
            'success'  => true,
            'total'    => count($results),
            'synced'   => $succeeded,
            'failed'   => $failed,
            'results'  => $results,
            'timestamp'=> now()->toIso8601String(),
        ];
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Helpers
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function extractSyncData(array $rawData, string $marketplace): ?array
    {
        $content = $rawData['results'][0]['content'] ?? null;

        if (!$content) {
            return null;
        }

        $price = null;

        if ($marketplace === 'amazon') {
            $price = $this->parsePrice($content['price'] ?? $content['price_upper'] ?? $content['pricing'][0]['price'] ?? null);
            $inStock = ($content['stock'] ?? '') === 'In Stock' || ($content['is_available'] ?? false);
            return [
                'price'        => $price,
                'availability' => $inStock ? 'in_stock' : 'out_of_stock',
            ];
        }

        // Generic
        if (is_array($content)) {
            return [
                'price'        => $this->parsePrice($content['price'] ?? null),
                'availability' => $content['availability'] ?? 'unknown',
            ];
        }

        return null;
    }

    private function parsePrice(mixed $price): ?float
    {
        if ($price === null) return null;
        if (is_numeric($price)) return (float) $price;

        if (is_string($price)) {
            $cleaned = preg_replace('/[^0-9.]/', '', $price);
            $val     = (float) $cleaned;
            return $val > 0 ? $val : null;
        }

        if (is_array($price)) {
            foreach (['value', 'amount', 'price'] as $key) {
                if (isset($price[$key])) return $this->parsePrice($price[$key]);
            }
        }

        return null;
    }
}

