<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BestsellersService
 *
 * Fetches bestselling/trending products from foreign marketplaces.
 * Supports two backends:
 *   1. Apify  — Runs cloud scraping actors (Amazon, Walmart, eBay, AliExpress, Alibaba)
 *   2. Oxylabs — Real-time scraping API (fallback)
 *
 * Ported from Supabase `apify-bestsellers` + `fetch-bestsellers`.
 */
class BestsellersService
{
    private const OXYLABS_URL = 'https://realtime.oxylabs.io/v1/queries';

    // Default Apify actors per marketplace (with fallback order)
    private const DEFAULT_ACTORS = [
        'amazon'     => ['curious_coder~amazon-scraper', 'junglee~amazon-bestsellers', 'logical_scrapers~amazon-search-scraper'],
        'walmart'    => ['epctex~walmart-scraper'],
        'ebay'       => ['apify~ebay-scraper', 'canadesk~ebay-product-scraper'],
        'aliexpress' => ['epctex~aliexpress-scraper'],
        'alibaba'    => ['epctex~alibaba-scraper'],
    ];

    // Amazon bestseller category URLs
    private const AMAZON_CATEGORIES = [
        'electronics' => 'https://www.amazon.com/Best-Sellers-Electronics/zgbs/electronics/',
        'fashion'     => 'https://www.amazon.com/Best-Sellers-Clothing-Shoes-Jewelry/zgbs/fashion/',
        'home'        => 'https://www.amazon.com/Best-Sellers-Home-Kitchen/zgbs/home-garden/',
        'beauty'      => 'https://www.amazon.com/Best-Sellers-Beauty-Personal-Care/zgbs/beauty/',
        'sports'      => 'https://www.amazon.com/Best-Sellers-Sports-Outdoors/zgbs/sporting-goods/',
        'toys'        => 'https://www.amazon.com/Best-Sellers-Toys-Games/zgbs/toys-and-games/',
        'health'      => 'https://www.amazon.com/Best-Sellers-Health-Household/zgbs/hpc/',
        'computers'   => 'https://www.amazon.com/Best-Sellers-Computers-Accessories/zgbs/pc/',
        'kitchen'     => 'https://www.amazon.com/Best-Sellers-Kitchen-Dining/zgbs/kitchen/',
        'baby'        => 'https://www.amazon.com/Best-Sellers-Baby/zgbs/baby-products/',
        'pet'         => 'https://www.amazon.com/Best-Sellers-Pet-Supplies/zgbs/pet-supplies/',
        'automotive'  => 'https://www.amazon.com/Best-Sellers-Automotive/zgbs/automotive/',
        'grocery'     => 'https://www.amazon.com/Best-Sellers-Grocery-Gourmet-Food/zgbs/grocery/',
        'default'     => 'https://www.amazon.com/Best-Sellers/zgbs/',
    ];

    // ──────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────

    /**
     * Fetch bestsellers.
     *
     * @param  string      $marketplace  amazon|walmart|ebay|aliexpress|alibaba
     * @param  string|null $category     Optional category (electronics, fashion, etc.)
     * @param  string|null $searchQuery  Override search term
     * @param  string|null $customUrl    Specific URL to scrape (Amazon bestseller page)
     * @param  int         $limit        Max products to return
     * @param  int         $offset       Pagination offset
     * @return array
     */
    public function fetch(
        string  $marketplace  = 'amazon',
        ?string $category     = null,
        ?string $searchQuery  = null,
        ?string $customUrl    = null,
        int     $limit        = 20,
        int     $offset       = 0,
    ): array {
        $apifyToken  = IorSetting::get('apify_api_token') ?? env('APIFY_API_TOKEN');
        $apifyActive = (bool) IorSetting::get('apify_active', false);

        $searchTerm = $searchQuery ?? ($category ? "bestseller {$category}" : 'bestseller');

        // ── Apify path ──────────────────────────────────────────
        if ($apifyActive && $apifyToken) {
            $result = $this->fetchViaApify($marketplace, $category, $searchTerm, $customUrl, $limit, $offset, $apifyToken);
            if ($result['success']) {
                return $result;
            }
            Log::warning('[IOR Bestsellers] Apify failed, falling back to Oxylabs: ' . ($result['error'] ?? ''));
        }

        // ── Oxylabs fallback ────────────────────────────────────
        $fallback = (bool) IorSetting::get('apify_fallback_to_oxylabs', true);
        if ($fallback) {
            return $this->fetchViaOxylabs($marketplace, $category, $searchTerm, $limit);
        }

        return ['success' => false, 'error' => 'No scraping service configured or enabled.'];
    }

    // ──────────────────────────────────────────────────────────────
    // APIFY
    // ──────────────────────────────────────────────────────────────

    private function fetchViaApify(
        string  $marketplace,
        ?string $category,
        string  $searchTerm,
        ?string $customUrl,
        int     $limit,
        int     $offset,
        string  $apiToken,
    ): array {
        $actors = self::DEFAULT_ACTORS[$marketplace] ?? self::DEFAULT_ACTORS['amazon'];
        $lastError = '';

        foreach ($actors as $actorId) {
            $input    = $this->buildApifyInput($actorId, $marketplace, $searchTerm, $category, $customUrl, $limit, $offset);
            $url      = "https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items?token={$apiToken}";

            $response = Http::timeout(120)->post($url, $input);

            if ($response->status() === 401) {
                return ['success' => false, 'error' => 'Invalid Apify API token.'];
            }
            if ($response->failed()) {
                $lastError = "Actor {$actorId} failed ({$response->status()})";
                continue;
            }

            $items = $response->json();
            if (!is_array($items) || empty($items)) {
                $lastError = "Actor {$actorId} returned no results.";
                continue;
            }

            $products = collect($items)
                ->skip($offset)
                ->take($limit)
                ->values()
                ->map(fn($item, $i) => $this->normalizeApifyProduct($item, $marketplace, $i))
                ->filter(fn($p) => !empty($p['title']) && !empty($p['url']))
                ->values()
                ->toArray();

            Log::info("[IOR Bestsellers] Apify actor {$actorId} returned " . count($products) . " products.");

            return [
                'success'     => true,
                'products'    => $products,
                'count'       => count($products),
                'marketplace' => $marketplace,
                'provider'    => 'apify',
                'actor'       => $actorId,
                'hasMore'     => count($products) >= $limit,
            ];
        }

        return ['success' => false, 'error' => $lastError ?: 'All Apify actors failed.'];
    }

    private function buildApifyInput(string $actorId, string $marketplace, string $term, ?string $category, ?string $customUrl, int $limit, int $offset): array
    {
        $categoryUrl = ($category && isset(self::AMAZON_CATEGORIES[$category]))
            ? self::AMAZON_CATEGORIES[$category]
            : self::AMAZON_CATEGORIES['default'];

        // curious_coder~amazon-scraper
        if (str_contains($actorId, 'curious_coder')) {
            $url = $customUrl ?? ($category ? $categoryUrl : "https://www.amazon.com/s?k=" . urlencode($term));
            return ['startUrls' => [['url' => $url]], 'maxItems' => $limit + $offset];
        }

        // junglee~amazon-bestsellers
        if (str_contains($actorId, 'junglee~amazon-bestsellers')) {
            return [
                'categoryUrls'          => [$customUrl ?? $categoryUrl],
                'maxItemsPerStartUrl'   => min($limit + $offset, 100),
                'depthOfCrawl'          => 1,
                'detailedInformation'   => true,
                'useCaptchaSolver'      => false,
            ];
        }

        // logical_scrapers
        if (str_contains($actorId, 'logical_scrapers')) {
            return ['keywords' => [$term], 'maxResults' => $limit];
        }

        // Walmart
        if (str_contains($actorId, 'walmart')) {
            return ['searchKeywords' => [$term], 'maxItems' => $limit, 'proxy' => ['useApifyProxy' => true]];
        }

        // eBay
        if (str_contains($actorId, 'ebay')) {
            return ['search' => $term, 'maxItems' => $limit];
        }

        // Default
        return ['query' => $term, 'search' => $term, 'maxItems' => $limit];
    }

    private function normalizeApifyProduct(array $item, string $marketplace, int $index): array
    {
        $price = null;
        foreach (['priceAmount', 'price', 'currentPrice'] as $key) {
            if (isset($item[$key])) {
                $raw   = $item[$key];
                $price = is_numeric($raw) ? (float) $raw : (float) preg_replace('/[^0-9.]/', '', (string) $raw);
                if ($price > 0) break;
                $price = null;
            }
        }

        $currency = $item['priceCurrency'] ?? $item['currency'] ?? 'USD';
        if ($currency === '$') $currency = 'USD';

        $url = $item['url'] ?? $item['link'] ?? $item['productUrl'] ?? $item['detailPageURL'] ?? '';
        $image = $item['thumbnail'] ?? $item['image'] ?? $item['mainImage'] ?? $item['primaryImage'] ?? (is_array($item['images'] ?? null) ? $item['images'][0] : null) ?? '';

        $rating = null;
        foreach (['stars', 'rating'] as $key) {
            if (isset($item[$key])) {
                $rating = is_numeric($item[$key]) ? (float) $item[$key] : null;
                if ($rating) break;
            }
        }

        $rank  = $item['position'] ?? ($index + 1);
        $asin  = $item['asin'] ?? $item['sku'] ?? $item['productId'] ?? null;
        $title = $item['name'] ?? $item['title'] ?? $item['productName'] ?? 'Unknown Product';

        return [
            'title'       => $title,
            'price_usd'   => $price,
            'currency'    => $currency,
            'image_url'   => $image,
            'url'         => $url,
            'rating'      => $rating,
            'reviewCount' => $item['ratingsCount'] ?? $item['reviewCount'] ?? $item['numberOfReviews'] ?? null,
            'rank'        => $rank,
            'asin'        => $asin,
            'marketplace' => $marketplace,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // OXYLABS FALLBACK
    // ──────────────────────────────────────────────────────────────

    private function fetchViaOxylabs(string $marketplace, ?string $category, string $searchTerm, int $limit): array
    {
        $username = env('OXYLABS_USERNAME');
        $password = env('OXYLABS_PASSWORD');

        if (!$username || !$password) {
            return ['success' => false, 'error' => 'Oxylabs not configured (OXYLABS_USERNAME/PASSWORD).'];
        }

        $source = $marketplace === 'amazon' ? 'amazon' : 'universal_ecommerce';
        $url    = match ($marketplace) {
            'amazon'  => "https://www.amazon.com/s?k=" . urlencode($searchTerm),
            'walmart' => "https://www.walmart.com/search?q=" . urlencode($searchTerm),
            'ebay'    => "https://www.ebay.com/sch/i.html?_nkw=" . urlencode($searchTerm),
            default   => "https://www.amazon.com/s?k=" . urlencode($searchTerm),
        };

        $auth     = 'Basic ' . base64_encode("{$username}:{$password}");
        $response = Http::withHeaders(['Authorization' => $auth])
            ->timeout(60)
            ->post(self::OXYLABS_URL, ['source' => $source, 'url' => $url, 'render' => 'html', 'parse' => true]);

        if ($response->failed()) {
            return ['success' => false, 'error' => 'Oxylabs API error: ' . $response->status()];
        }

        $data    = $response->json();
        $content = $data['results'][0]['content'] ?? null;
        $items   = is_array($content) ? collect($content)->take($limit)->toArray() : [];

        return [
            'success'     => true,
            'products'    => $items,
            'count'       => count($items),
            'marketplace' => $marketplace,
            'provider'    => 'oxylabs',
            'hasMore'     => count($items) >= $limit,
        ];
    }
}



