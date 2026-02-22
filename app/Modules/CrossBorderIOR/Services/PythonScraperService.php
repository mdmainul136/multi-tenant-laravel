<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PythonScraperService — HTTP Client for FastAPI Scraper Microservice.
 *
 * Previous approach: Laravel spawned a Python subprocess per scrape (slow, blocking).
 * New approach:       HTTP POST to persistent FastAPI service (fast, async, pooled).
 *
 * FastAPI service endpoint: POST http://localhost:8001/scrape
 * See: python/app/main.py
 */
class PythonScraperService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(
        private ProxyRegistryService $proxyRegistry
    ) {
        $this->baseUrl = config('services.python_scraper.base_url', 'http://localhost:8001');
        $this->apiKey  = config('services.python_scraper.api_key', 'ior-scraper-secret-change-me');
    }

    /**
     * Scrape a single product URL via the FastAPI microservice.
     */
    public function scrapeProduct(string $url, ?int $tenantId = null): array
    {
        $sessionId = Str::random(12);
        Log::info("[IOR Scraper] Scraping URL: $url (Session: $sessionId)");

        // 1. Get Healthy Proxy from Landlord Registry
        $proxyArg = $this->resolveProxy($tenantId, $sessionId);

        // 2. Call FastAPI microservice
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])
                ->timeout(45)
                ->post("{$this->baseUrl}/scrape", [
                    'url'        => $url,
                    'proxy'      => $proxyArg,
                    'user_agent' => $this->proxyRegistry->getRandomUserAgent(),
                ]);

            if ($response->failed()) {
                Log::error("[IOR Scraper] HTTP {$response->status()}: " . $response->body());
                throw new \RuntimeException("Scraper service returned HTTP {$response->status()}");
            }

            $body = $response->json();

            if (!($body['success'] ?? false)) {
                $error = $body['error'] ?? 'Unknown scraper error';
                $this->reportProxyResult(false, $sessionId, $error);
                throw new \RuntimeException("Scraper error: $error");
            }

            $this->reportProxyResult(true, $sessionId);

            return $this->normalise($body['data']);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("[IOR Scraper] Cannot connect to FastAPI service: {$e->getMessage()}");
            throw new \RuntimeException(
                "Scraper service unavailable. Ensure the FastAPI service is running on {$this->baseUrl}"
            );
        }
    }

    /**
     * Bulk scrape multiple URLs concurrently via the FastAPI microservice.
     */
    public function bulkScrape(array $urls, ?int $tenantId = null): array
    {
        $proxyArg = $this->resolveProxy($tenantId, 'bulk');

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])
                ->timeout(120)
                ->post("{$this->baseUrl}/bulk-scrape", [
                    'urls'       => $urls,
                    'proxy'      => $proxyArg,
                    'user_agent' => $this->proxyRegistry->getRandomUserAgent(),
                ]);

            if ($response->failed()) {
                throw new \RuntimeException("Bulk scrape failed: HTTP {$response->status()}");
            }

            $body = $response->json();

            return [
                'total'     => $body['total'] ?? count($urls),
                'succeeded' => $body['succeeded'] ?? 0,
                'failed'    => $body['failed'] ?? 0,
                'results'   => collect($body['results'] ?? [])
                    ->map(fn($r) => $r['success'] ? $this->normalise($r['data']) : ['error' => $r['error']])
                    ->all(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException("Scraper service unavailable: {$e->getMessage()}");
        }
    }

    /**
     * Quick price quote via the FastAPI microservice.
     */
    public function quote(float $priceUsd, float $weightKg = 0.5, array $options = []): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])
            ->timeout(10)
            ->post("{$this->baseUrl}/quote", [
                'price_usd'     => $priceUsd,
                'weight_kg'     => $weightKg,
                'exchange_rate' => $options['exchange_rate'] ?? 120.0,
                'duty_percent'  => $options['duty_percent'] ?? 25.0,
                'shipping_usd'  => $options['shipping_usd'] ?? 15.0,
            ]);

        return $response->json();
    }

    /**
     * Check if the FastAPI scraper service is healthy.
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->ok() && ($response->json('status') === 'healthy');
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Resolve the best proxy to use for this request.
     */
    private function resolveProxy(?int $tenantId, string $sessionId): ?string
    {
        // 1. Try Landlord global proxy registry
        $proxyModel = $this->proxyRegistry->getProxy(null, $sessionId);

        if ($proxyModel) {
            $proxyArg = $this->proxyRegistry->getRotatedProxyString($proxyModel, $sessionId);
            Log::info("[IOR Scraper] Using Global Proxy ID: {$proxyModel->id} ({$proxyModel->provider})");
            return $proxyArg;
        }

        // 2. Fallback to tenant-level proxy settings
        if ($tenantId) {
            $settings = \DB::table('ior_scraper_settings')->where('tenant_id', $tenantId)->first();
            if ($settings && $settings->use_proxy && $settings->proxy_host) {
                return "http://" . ($settings->proxy_user ? "{$settings->proxy_user}:{$settings->proxy_password}@" : "") . $settings->proxy_host . ":" . ($settings->proxy_port ?? 80);
            }
        }

        return null;
    }

    /**
     * Report proxy success/failure for health tracking.
     */
    private function reportProxyResult(bool $success, string $sessionId, string $error = ''): void
    {
        // Only if we used a global proxy
        $proxyModel = $this->proxyRegistry->getProxy(null, $sessionId);
        if (!$proxyModel) return;

        if ($success) {
            $this->proxyRegistry->reportSuccess($proxyModel->id);
        } else {
            $this->proxyRegistry->reportFailure($proxyModel->id, "Scraper error: $error");
        }
    }

    /**
     * Normalise scraper output to standard product array.
     */
    private function normalise(array $raw): array
    {
        return [
            'title'         => $raw['title'] ?? 'Unknown Product',
            'price_usd'     => (float) ($raw['price_usd'] ?? 0),
            'currency'      => $raw['currency'] ?? 'USD',
            'images'        => $raw['images'] ?? [],
            'thumbnail'     => $raw['thumbnail'] ?? null,
            'description'   => $raw['description'] ?? '',
            'features'      => $raw['features'] ?? [],
            'rating'        => (float) ($raw['rating'] ?? 0),
            'review_count'  => (int) ($raw['review_count'] ?? 0),
            'weight_kg'     => 0.5,
            'variants'      => $raw['variants'] ?? [],
            'asin'          => null,
            'brand'         => null,
            'availability'  => $raw['availability'] ?? 'unknown',
            'marketplace'   => $raw['marketplace'] ?? 'unknown',
            'source_url'    => $raw['source_url'] ?? '',
            'scraped_at'    => now()->toISOString(),
        ];
    }
}
