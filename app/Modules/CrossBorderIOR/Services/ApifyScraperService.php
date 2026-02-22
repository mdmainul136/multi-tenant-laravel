<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CrossBorderIOR\IorSetting;

class ApifyScraperService
{
    private string $apiToken;
    private const BASE_URL = 'https://api.apify.com/v2';

    // Primary actor per marketplace (matching Supabase apify-scrape)
    private const ACTORS = [
        'amazon'   => 'junglee~amazon-crawler',
        'ebay'     => 'apify~e-commerce-scraping-tool',
        'walmart'  => 'apify~e-commerce-scraping-tool',
        'alibaba'  => 'apify~e-commerce-scraping-tool',
        'default'  => 'apify~e-commerce-scraping-tool',
    ];

    public function __construct()
    {
        $this->apiToken = IorSetting::get('apify_api_token', config('services.apify.token', ''));
    }

    /**
     * Detect marketplace from URL.
     */
    public static function detectMarketplace(string $url): string
    {
        if (str_contains($url, 'amazon')) return 'amazon';
        if (str_contains($url, 'ebay'))   return 'ebay';
        if (str_contains($url, 'walmart'))return 'walmart';
        if (str_contains($url, 'alibaba'))return 'alibaba';
        if (str_contains($url, 'aliexpress')) return 'aliexpress';
        return 'other';
    }

    /**
     * Scrape a single product URL. Returns normalised product data array.
     */
    public function scrapeProduct(string $url): array
    {
        if (empty($this->apiToken)) {
            throw new \RuntimeException('Apify API token not configured. Set it in IOR Settings → Scraper.');
        }

        $marketplace = self::detectMarketplace($url);
        $actor       = self::ACTORS[$marketplace] ?? self::ACTORS['default'];

        Log::info("[IOR Scraper] Scraping $marketplace product: $url (actor: $actor)");

        $startedAt = now();

        // Run actor synchronously (waitForFinish=120s)
        $response = Http::withToken($this->apiToken)
            ->timeout(130)
            ->post(self::BASE_URL . "/acts/{$actor}/run-sync-get-dataset-items", [
                'startUrls' => [['url' => $url]],
                'maxItems'  => 1,
            ]);

        if ($response->failed()) {
            Log::error("[IOR Scraper] Apify run failed: " . $response->body());
            throw new \RuntimeException('Scraper API error: ' . $response->status());
        }

        $items = $response->json();

        if (empty($items) || !is_array($items)) {
            throw new \RuntimeException('No product data returned from scraper');
        }

        $raw = $items[0];

        return $this->normalise($raw, $marketplace, $url, (int) now()->diffInMilliseconds($startedAt));
    }

    /**
     * Normalise raw Apify actor output to a standard product array.
     */
    private function normalise(array $raw, string $marketplace, string $url, int $durationMs): array
    {
        // Title
        $title = $raw['title']
            ?? $raw['name']
            ?? $raw['productName']
            ?? 'Unknown Product';

        // Price extraction
        $price = $this->extractPrice($raw['price'] ?? $raw['currentPrice'] ?? $raw['finalPrice'] ?? null);

        // Images
        $images = $this->extractImages($raw);

        // Reviews
        $rating     = (float) ($raw['stars'] ?? $raw['rating'] ?? $raw['averageRating'] ?? 0);
        $reviewCount= (int)   ($raw['numberOfReviews'] ?? $raw['reviewsCount'] ?? $raw['ratingsCount'] ?? 0);

        // Weight (kg) — try to extract or default
        $weight = $this->extractWeight($raw) ?? 0.5;

        // Variants
        $variants = $this->extractVariants($raw);

        return [
            'title'         => (string) $title,
            'price_usd'     => $price,
            'currency'      => $raw['currency'] ?? 'USD',
            'images'        => $images,
            'thumbnail'     => $images[0] ?? null,
            'description'   => $raw['description'] ?? $raw['about'] ?? '',
            'rating'        => $rating,
            'review_count'  => $reviewCount,
            'weight_kg'     => $weight,
            'variants'      => $variants,
            'asin'          => $raw['asin'] ?? $raw['productId'] ?? null,
            'brand'         => $raw['brand'] ?? $raw['seller'] ?? null,
            'availability'  => $raw['availability'] ?? ($price ? 'in_stock' : 'out_of_stock'),
            'marketplace'   => $marketplace,
            'source_url'    => $url,
            'scraped_at'    => now()->toISOString(),
            'duration_ms'   => $durationMs,
        ];
    }

    private function extractPrice(mixed $field): ?float
    {
        if ($field === null) return null;
        if (is_numeric($field)) return (float) $field;
        if (is_array($field)) {
            return isset($field['value']) ? (float) $field['value'] : null;
        }
        if (is_string($field)) {
            $cleaned = preg_replace('/[^0-9.]/', '', $field);
            return $cleaned !== '' ? (float) $cleaned : null;
        }
        return null;
    }

    private function extractImages(array $raw): array
    {
        // junglee~amazon-crawler uses 'images' array of objects or strings
        if (isset($raw['images']) && is_array($raw['images'])) {
            return array_values(array_filter(array_map(function ($img) {
                if (is_string($img)) return $img;
                return $img['hiRes'] ?? $img['large'] ?? $img['thumb'] ?? null;
            }, $raw['images'])));
        }

        if (isset($raw['thumbnailUrl'])) return [$raw['thumbnailUrl']];
        if (isset($raw['imageUrl']))     return [$raw['imageUrl']];

        return [];
    }

    private function extractWeight(array $raw): ?float
    {
        // Try product specifications
        if (isset($raw['specifications']) && is_array($raw['specifications'])) {
            foreach ($raw['specifications'] as $spec) {
                $key = strtolower($spec['name'] ?? '');
                if (str_contains($key, 'weight')) {
                    $val = preg_replace('/[^0-9.]/', '', $spec['value'] ?? '');
                    if ($val) {
                        $weight = (float) $val;
                        // Convert pounds to kg if needed
                        if (str_contains(strtolower($spec['value'] ?? ''), 'pound') || str_contains(strtolower($spec['value'] ?? ''), ' lb')) {
                            $weight = round($weight * 0.453592, 3);
                        }
                        return $weight;
                    }
                }
            }
        }

        if (isset($raw['weight'])) {
            $val = preg_replace('/[^0-9.]/', '', (string) $raw['weight']);
            return $val ? (float) $val : null;
        }

        return null;
    }

    private function extractVariants(array $raw): array
    {
        if (isset($raw['variants']) && is_array($raw['variants'])) {
            return array_slice($raw['variants'], 0, 50); // cap at 50
        }

        return [];
    }
}



