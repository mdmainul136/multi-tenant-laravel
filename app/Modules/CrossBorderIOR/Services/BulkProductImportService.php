<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BulkProductImportService
 *
 * Accepts a list of marketplace URLs, scrapes each one via Oxylabs or Apify,
 * normalises the result and saves to `catalog_products`.
 *
 * Ported from Supabase `bulk-import-products`.
 * Max batch size: 10 items per request (to avoid timeouts).
 */
class BulkProductImportService
{
    private const OXYLABS_URL  = 'https://realtime.oxylabs.io/v1/queries';
    private const BATCH_LIMIT  = 10;
    private const REQUEST_DELAY_MS = 1000; // ms between scrape calls

    public function __construct(
        private ApifyScraperService   $apify,
        private PythonScraperService  $python,
        private BlockedSourceService  $blocked,
        private ProductMediaService   $media,
        private ScraperBillingService $billing
    ) {}

    // ASIN regex patterns
    private const ASIN_PATTERNS = [
        '/\/dp\/([A-Z0-9]{10})/i',
        '/\/gp\/product\/([A-Z0-9]{10})/i',
        '/\/gp\/aw\/d\/([A-Z0-9]{10})/i',
        '/\/product\/([A-Z0-9]{10})/i',
        '/asin=([A-Z0-9]{10})/i',
    ];

    // Per-marketplace Oxylabs config
    private const MARKETPLACE_CONFIGS = [
        'amazon'   => ['source' => 'amazon_product', 'method' => 'asin'],
        'ebay'     => ['source' => 'universal_ecommerce', 'method' => 'url'],
        'walmart'  => ['source' => 'universal_ecommerce', 'method' => 'url'],
        'alibaba'  => ['source' => 'universal_ecommerce', 'method' => 'url'],
        'aliexpress'=>['source' => 'universal_ecommerce', 'method' => 'url'],
    ];

    // ──────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────

    /**
     * @param  array $items  [['url' => '...', 'name' => '...'], ...]
     * @param  string $provider 'oxylabs' or 'apify'
     * @return array  {success, summary{total, successful, failed}, results[]}
     */
    public function import(array $items, string $provider = 'oxylabs'): array
    {
        $username = env('OXYLABS_USERNAME');
        $password = env('OXYLABS_PASSWORD');

        if ($provider === 'oxylabs' && (!$username || !$password)) {
            return [
                'success' => false,
                'error'   => 'Oxylabs scraping service not configured. Set OXYLABS_USERNAME and OXYLABS_PASSWORD in .env.',
            ];
        }

        $items   = array_slice($items, 0, self::BATCH_LIMIT);
        $auth    = 'Basic ' . base64_encode("{$username}:{$password}");
        $results = [];

        foreach ($items as $item) {
            $url  = trim($item['url'] ?? '');
            $name = trim($item['name'] ?? '');

            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                $results[] = ['url' => $url, 'success' => false, 'error' => 'Invalid URL'];
                continue;
            }

            if ($this->blocked->isBlocked($url)) {
                $results[] = ['url' => $url, 'success' => false, 'error' => 'Domain is blocked by admin'];
                continue;
            }

            // Duplicate check
            $existing = \DB::table('ec_products')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ior_attributes, '$.original_url')) = ?", [$url])
                ->exists();

            if ($existing) {
                $results[] = ['url' => $url, 'success' => false, 'error' => 'Already imported'];
                continue;
            }

            $product = $this->scrapeOne($url, $auth, $provider);

            if (!$product) {
                $this->billing->logScrape($provider, 'failed', $url, null, ['error' => 'Scrape returned null']);
                $results[] = ['url' => $url, 'success' => false, 'error' => 'Failed to scrape or unsupported URL'];
                continue;
            }

            // Billing: Log success
            $this->billing->logScrape($provider, 'success', $url);

            try {
                $slug      = $this->makeSlug($name ?: $product['title']);
                $productId = \DB::table('ec_products')->insertGetId([
                    'name'              => $name ?: $product['title'],
                    'slug'              => $slug,
                    'sku'               => $product['sku'] ?? ('IOR-' . strtoupper(Str::random(8))),
                    'description'       => '[Draft] Content pending review and rewrite.',
                    'short_description' => '[Draft] Sourced item.',
                    'price'             => 0,
                    'cost'              => $product['price'],
                    'image_url'         => $this->media->rehostImage($product['primaryImage']),
                    'gallery'           => json_encode($this->media->rehostGallery($product['images'] ?? [])),
                    'is_active'         => false,
                    'product_type'      => 'foreign',
                    'content_status'    => 'pending_rewrite',
                    'ior_attributes'    => json_encode([
                        'original_url'  => $url,
                        'marketplace'   => $product['marketplace'],
                        'currency'      => 'USD',
                        'imported_at'   => now()->toIso8601String(),
                        'bulk_import'   => true,
                    ]),
                    'source_metadata'   => json_encode([
                        'original_title'       => $product['title'],
                        'original_description' => $product['description'],
                        'original_features'    => $product['features'] ?? [],
                        'brand'                => $product['brand'] ?? null,
                        'rating'               => $product['rating'] ?? 0,
                        'review_count'         => $product['review_count'] ?? 0,
                        'review_snippets'      => $product['review_snippets'] ?? [],
                    ]),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                // 2. Variants: Store variants if available
                if (!empty($product['variants'])) {
                    foreach ($product['variants'] as $v) {
                        $type = $v['type'] ?? 'option';
                        foreach ($v['values'] as $val) {
                            $name = is_array($val) ? ($val['name'] ?? 'Unknown') : $val;
                            $imageUrl = is_array($val) ? ($val['image'] ?? null) : null;
                            
                            \DB::table('ior_product_variants')->insert([
                                'product_id'   => $productId,
                                'variant_key'  => md5($type . $name),
                                'attributes'   => json_encode(['type' => $type, 'value' => $name]),
                                'image_url'    => $imageUrl,
                                'price_usd'    => $product['price'], 
                                'stock_status' => 'in_stock',
                                'created_at'   => now(),
                                'updated_at'   => now(),
                            ]);
                        }
                    }
                }

                // 3. Tracking: Create a row in ior_product_sources for auto-sync
                \DB::table('ior_product_sources')->insert([
                    'product_id'      => $productId,
                    'source_url'      => $url,
                    'marketplace'     => $product['marketplace'],
                    'stock_status'    => ($product['availability'] ?? 'in_stock'),
                    'last_usd_price'  => $product['price'],
                    'last_checked_at' => now(),
                    'checksum_hash'   => md5(json_encode($product)),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                $results[] = [
                    'url'        => $url,
                    'success'    => true,
                    'product_id' => $productId,
                    'name'       => $name ?: $product['title'],
                    'price'      => $product['price'],
                    'marketplace'=> $product['marketplace'],
                ];

                Log::info("[IOR BulkImport] Saved: {$url} → ID {$productId}");
            } catch (\Exception $e) {
                Log::error("[IOR BulkImport] DB save error for {$url}: " . $e->getMessage());
                $results[] = ['url' => $url, 'success' => false, 'error' => 'Database save failed'];
            }

            // Rate-limit courtesy delay between scrape requests
            usleep(self::REQUEST_DELAY_MS * 1000);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));

        return [
            'success' => true,
            'summary' => [
                'total'      => count($results),
                'successful' => $successCount,
                'failed'     => count($results) - $successCount,
            ],
            'results' => $results,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // SCRAPING (Oxylabs)
    // ──────────────────────────────────────────────────────────────

    private function scrapeOne(string $url, string $auth, string $provider = 'oxylabs'): ?array
    {
        $marketplace = $this->detectMarketplace($url);

        if (!$marketplace) {
            Log::warning("[IOR BulkImport] Unsupported URL: {$url}");
            return null;
        }

        if ($provider === 'python') {
            try {
                $tenantId = \DB::table('ior_scraper_settings')->value('tenant_id');
                $scraped = $this->python->scrapeProduct($url, $tenantId);
                return [
                    'title'        => $scraped['title'],
                    'description'  => $scraped['description'],
                    'features'     => $scraped['features'],
                    'images'       => $scraped['images'],
                    'primaryImage' => $scraped['thumbnail'],
                    'price'        => $scraped['price_usd'],
                    'brand'        => $scraped['brand'] ?? null,
                    'sku'          => null,
                    'availability' => $scraped['availability'],
                    'marketplace'  => $scraped['marketplace'],
                    'rating'       => $scraped['rating'],
                    'review_count' => $scraped['review_count'],
                    'originalUrl'  => $url,
                ];
            } catch (\Exception $e) {
                Log::error("[IOR BulkImport] Python scrape exception for {$url}: " . $e->getMessage());
                return null;
            }
        }

        if ($provider === 'apify') {
            try {
                $scraped = $this->apify->scrapeProduct($url);
                return [
                    'title'        => $scraped['title'],
                    'description'  => $scraped['description'],
                    'features'     => [], // Apify normalise handles this differently, but we can extend if needed
                    'images'       => $scraped['images'],
                    'primaryImage' => $scraped['thumbnail'],
                    'price'        => $scraped['price_usd'],
                    'brand'        => $scraped['brand'],
                    'sku'          => $scraped['asin'] ?? ('AP-' . strtoupper(Str::random(8))),
                    'availability' => $scraped['availability'],
                    'marketplace'  => $scraped['marketplace'],
                    'originalUrl'  => $url,
                ];
            } catch (\Exception $e) {
                Log::error("[IOR BulkImport] Apify scrape exception for {$url}: " . $e->getMessage());
                return null;
            }
        }

        $config = self::MARKETPLACE_CONFIGS[$marketplace];

        try {
            if ($config['method'] === 'asin') {
                $asin = $this->extractAsin($url);
                if (!$asin) return null;

                $payload = [
                    'source'       => $config['source'],
                    'query'        => $asin,
                    'geo_location' => '90210',
                    'parse'        => true,
                ];
            } else {
                $payload = [
                    'source' => 'universal_ecommerce',
                    'url'    => $url,
                    'render' => 'html',
                    'parse'  => true,
                ];
            }

            $response = Http::withHeaders(['Authorization' => $auth])
                ->timeout(45)
                ->post(self::OXYLABS_URL, $payload);

            if ($response->failed()) {
                Log::warning("[IOR BulkImport] Oxylabs {$response->status()} for {$url}");
                return null;
            }

            $raw = $response->json();
            return $this->normalise($raw, $marketplace, $url);
        } catch (\Exception $e) {
            Log::error("[IOR BulkImport] Scrape exception for {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function normalise(array $raw, string $marketplace, string $originalUrl): array
    {
        $content = $raw['results'][0]['content'] ?? null;

        $base = [
            'title'        => 'Imported Product',
            'description'  => '',
            'features'     => [],
            'images'       => [],
            'primaryImage' => null,
            'price'        => null,
            'brand'        => null,
            'sku'          => null,
            'availability' => 'unknown',
            'marketplace'  => $marketplace,
            'originalUrl'  => $originalUrl,
        ];

        if (!is_array($content)) {
            return $base;
        }

        if ($marketplace === 'amazon') {
            $rawPrice = $content['price'] ?? $content['price_upper'] ?? null;
            $price    = is_numeric($rawPrice) ? (float) $rawPrice
                : (float) preg_replace('/[^0-9.]/', '', (string) $rawPrice);

            return array_merge($base, [
                'title'        => $content['title']        ?? $base['title'],
                'description'  => $content['description']  ?? '',
                'features'     => is_array($content['bullet_points'] ?? null) ? $content['bullet_points'] : [],
                'images'       => is_array($content['images'] ?? null) ? $content['images'] : [],
                'primaryImage' => $content['images'][0]    ?? null,
                'price'        => $price > 0 ? $price : null,
                'brand'        => $content['brand']        ?? null,
                'sku'          => $content['asin']         ?? null,
                'availability' => ($content['is_available'] ?? false) ? 'in_stock' : 'out_of_stock',
            ]);
        }

        // Generic fallback for eBay, Walmart, Alibaba, etc.
        return array_merge($base, [
            'title'        => $content['title'] ?? $base['title'],
            'description'  => $content['description'] ?? '',
            'features'     => [],
            'primaryImage' => $content['main_image'] ?? $content['thumbnail'] ?? null,
            'price'        => isset($content['price']) ? (float) preg_replace('/[^0-9.]/', '', (string) $content['price']) : null,
            'availability' => $content['availability'] ?? 'unknown',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────

    private function detectMarketplace(string $url): ?string
    {
        $url = strtolower($url);
        if (str_contains($url, 'amazon.'))    return 'amazon';
        if (str_contains($url, 'ebay.'))      return 'ebay';
        if (str_contains($url, 'walmart.'))   return 'walmart';
        if (str_contains($url, 'alibaba.'))   return 'alibaba';
        if (str_contains($url, 'aliexpress.'))return 'aliexpress';
        return null;
    }

    private function extractAsin(string $url): ?string
    {
        foreach (self::ASIN_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $m)) return strtoupper($m[1]);
        }
        return null;
    }

    private function makeSlug(string $title): string
    {
        return Str::slug(Str::limit($title, 80)) . '-' . time();
    }

    private function makeBullets(array $features): ?string
    {
        $top = array_slice($features, 0, 3);
        return $top ? implode(' • ', $top) : null;
    }
}
