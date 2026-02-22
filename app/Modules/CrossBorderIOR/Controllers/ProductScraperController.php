<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CrossBorderIOR\Services\ApifyScraperService;
use App\Modules\CrossBorderIOR\Services\BulkProductImportService;
use App\Modules\CrossBorderIOR\Services\ProductPricingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductScraperController extends Controller
{
    public function __construct(
        private ApifyScraperService      $scraper,
        private ProductPricingCalculator $pricer,
        private BulkProductImportService $importer,
        private PythonScraperService     $pythonScraper,
        private \App\Services\SaaSWalletService $walletService,
    ) {}

    // ══════════════════════════════════════════════════════════════
    // SCRAPE  —  POST /ior/scrape
    // ══════════════════════════════════════════════════════════════

    /**
     * Scrape a single product URL and return product data + BDT quote.
     */
    public function scrape(Request $request): JsonResponse
    {
        $request->validate([
            'url'             => 'required|url|max:2000',
            'quantity'        => 'integer|min:1|max:100',
            'shipping_method' => 'in:air,sea',
            'provider'        => 'sometimes|string|in:apify,python',
        ]);

        $url            = $request->input('url');
        $quantity       = $request->integer('quantity', 1);
        $shippingMethod = $request->input('shipping_method', 'air');
        $provider       = $request->input('provider', 'apify');

        try {
            // Check balance before scraping
            $tenantId = $request->attributes->get('tenant_id');
            $scrapeCost = (float) \App\Models\CrossBorderIOR\IorSetting::get('scraping_cost_per_product', 0.10);

            if ($tenantId && !$this->walletService->hasSufficientBalance($tenantId, $scrapeCost)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient SaaS wallet balance to perform scraping.',
                ], 402);
            }

            if ($provider === 'python') {
                $product = $this->pythonScraper->scrapeProduct($url);
            } else {
                $product = $this->scraper->scrapeProduct($url);
            }

            // Charge and Increment Counter upon success
            if ($tenantId) {
                $this->walletService->debit($tenantId, $scrapeCost, 'scraper', "Scraping: {$url}", $url);
                
                // Tier Quota Increment
                $prefix = config('ior_quotas.redis_prefix', 'ior_quota:');
                $date = date('Y-m-d');
                $key = "{$prefix}{$tenantId}:scraping:{$date}";
                
                Cache::increment($key);
                if (!Cache::has($key . ':expiry')) {
                    Cache::put($key . ':expiry', true, 86400 * 2);
                }
            }

            $pricing = $this->pricer->calculate(
                usdPrice      : ($product['price_usd'] ?? 0) * $quantity,
                weightKg      : ($product['weight_kg'] ?? 0.5) * $quantity,
                productTitle  : $product['title'] ?? '',
                shippingMethod: $shippingMethod,
            );

            // Compliance Check (Global Governance)
            $compliance = app(\App\Modules\CrossBorderIOR\Services\ComplianceSafetyService::class)->check(
                $product['title'] ?? '', 
                $product['description'] ?? '',
                $product['origin_country'] ?? $request->input('origin_country')
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'product' => $product,
                    'pricing' => $pricing,
                    'quantity'=> $quantity,
                    'compliance' => $compliance,
                    'summary' => [
                        'product_name'        => $product['title'],
                        'marketplace'         => $product['marketplace'],
                        'source_price_usd'    => $product['price_usd'],
                        'estimated_price_bdt' => $pricing['estimated_price_bdt'],
                        'advance_amount'      => $pricing['advance_amount'],
                        'remaining_amount'    => $pricing['remaining_amount'],
                        'exchange_rate'       => $pricing['exchange_rate'],
                        'is_restricted'       => $compliance['is_restricted']
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[IOR Scrape] Error: ' . $e->getMessage());

            DB::table('ior_import_logs')->insert([
                'product_url'     => $url,
                'marketplace'     => ApifyScraperService::detectMarketplace($url),
                'scraper'         => 'apify',
                'status'          => 'failed',
                'error_message'   => $e->getMessage(),
                'request_payload' => json_encode(['url' => $url, 'quantity' => $quantity]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Product scraping failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // QUOTE  —  POST /ior/quote
    // ══════════════════════════════════════════════════════════════

    /**
     * Quick BDT price quote from manual USD price (no scraping needed).
     */
    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'price_usd'       => 'required|numeric|min:0.01',
            'weight_kg'       => 'numeric|min:0.01',
            'product_title'   => 'sometimes|string|max:255',
            'shipping_method' => 'in:air,sea',
            'quantity'        => 'integer|min:1',
        ]);

        $qty     = $request->integer('quantity', 1);
        $pricing = $this->pricer->calculate(
            usdPrice      : $request->float('price_usd') * $qty,
            weightKg      : $request->float('weight_kg', 0.5) * $qty,
            productTitle  : $request->string('product_title', ''),
            shippingMethod: $request->input('shipping_method', 'air'),
        );

        return response()->json(['success' => true, 'data' => $pricing]);
    }

    // ══════════════════════════════════════════════════════════════
    // BULK IMPORT  —  POST /ior/admin/bulk-import
    // ══════════════════════════════════════════════════════════════

    /**
     * Batch-import foreign products from URLs via Oxylabs scraping.
     *
     * Body: { items: [{ url, name? }, ...] }   max 10 items
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'items'        => 'required|array|min:1|max:10',
            'items.*.url'  => 'required|url|max:2000',
            'items.*.name' => 'nullable|string|max:300',
            'provider'     => 'sometimes|string|in:oxylabs,apify,python',
        ]);

        $tenantId = $request->attributes->get('tenant_id');
        $scrapeCost = (float) \App\Models\CrossBorderIOR\IorSetting::get('scraping_cost_per_product', 0.10);
        $totalCost = $scrapeCost * count($request->input('items'));

        if ($tenantId && !$this->walletService->hasSufficientBalance($tenantId, $totalCost)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient SaaS wallet balance for bulk import.',
            ], 402);
        }

        $result = $this->importer->import(
            $request->input('items'),
            $request->input('provider', 'oxylabs')
        );

        if ($result['success'] && $tenantId) {
            $this->walletService->debit($tenantId, $totalCost, 'scraper', "Bulk Import: " . count($request->input('items')) . " items");

            // Tier Quota Increment
            $prefix = config('ior_quotas.redis_prefix', 'ior_quota:');
            $date = date('Y-m-d');
            $count = count($request->input('items'));
            $key = "{$prefix}{$tenantId}:scraping:{$date}";

            Cache::increment($key, $count);
            if (!Cache::has($key . ':expiry')) {
                Cache::put($key . ':expiry', true, 86400 * 2);
            }
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // ══════════════════════════════════════════════════════════════
    // CATALOG  —  GET /ior/catalog
    // ══════════════════════════════════════════════════════════════

    /**
     * Browse scraped/imported products in catalog_products.
     *
     * Query: marketplace, status, availability, search, per_page
     */
    public function catalog(Request $request): JsonResponse
    {
        $query = DB::table('ec_products')->where('product_type', 'foreign')->whereNull('deleted_at');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($marketplace = $request->input('marketplace')) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(ior_attributes, '$.marketplace')) = ?",
                [$marketplace]
            );
        }

        if ($status = $request->input('is_active')) {
            $query->where('is_active', (bool) $status);
        }

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $products = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $products->items(),
            'meta'    => [
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Simulate IOR landed cost (Tax + Shipping + Exchange).
     */
    public function simulateLandedCost(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'price_usd'      => 'required|numeric|min:0.01',
            'hs_code'        => 'sometimes|string|max:20',
            'weight_kg'      => 'numeric|min:0.01',
            'length_cm'      => 'numeric|min:0',
            'width_cm'       => 'numeric|min:0',
            'height_cm'      => 'numeric|min:0',
            'origin_country' => 'nullable|string|size:3',
            'title'          => 'sometimes|string|max:300',
        ]);

        $simulator = app(\App\Modules\CrossBorderIOR\Services\LandedCostCalculatorService::class);
        
        $params = $request->only(['price_usd', 'hs_code', 'weight_kg', 'origin_country', 'title']);
        $params['dimensions'] = [
            'l' => $request->float('length_cm', 0),
            'w' => $request->float('width_cm', 0),
            'h' => $request->float('height_cm', 0),
        ];

        return response()->json([
            'success' => true,
            'data'    => $simulator->simulate($params, $request->attributes->get('tenant_id'))
        ]);
    }
}
