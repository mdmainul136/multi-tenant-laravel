<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorSetting;
use App\Modules\CrossBorderIOR\Services\AiContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiContentController extends Controller
{
    public function __construct(private AiContentService $ai) {}

    // ══════════════════════════════════════════════════════════════════════════
    // STATUS / CONFIG
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /ior/ai/status
     * Returns which AI providers are configured and which is active.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'available' => $this->ai->isAvailable(),
            'providers' => $this->ai->listModels(),
        ]);
    }

    /**
     * PUT /ior/ai/settings
     * Configure which provider/model to use.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gemini_api_key'      => 'nullable|string',
            'openai_api_key'      => 'nullable|string',
            'claude_api_key'      => 'nullable|string',
            'gemini_model'        => 'nullable|string|in:gemini-1.5-pro,gemini-1.5-flash,gemini-2.0-flash-exp',
            'openai_model'        => 'nullable|string|in:gpt-4o,gpt-4o-mini,gpt-4-turbo',
            'claude_model'        => 'nullable|string|in:claude-3-5-sonnet-20241022,claude-3-haiku-20240307,claude-3-opus-20240229',
            'ai_preferred_provider' => 'nullable|string|in:auto,gemini,openai,claude',
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                IorSetting::set($key, $value, 'ai');
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => 'AI settings updated.',
            'providers'=> (new AiContentService())->listModels(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONTENT GENERATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * POST /ior/ai/describe
     * Generate product description from raw product data.
     *
     * Body: { product: { title, features, category, price_bdt, ... }, lang: "both"|"en"|"bn" }
     */
    public function describe(Request $request): JsonResponse
    {
        $request->validate([
            'product'         => 'required|array',
            'product.title'   => 'required|string|max:300',
            'lang'            => 'nullable|in:both,en,bn',
        ]);

        return $this->runGeneration(fn() =>
            $this->ai->generateDescription($request->input('product'), $request->input('lang', 'both'))
        );
    }

    /**
     * POST /ior/ai/seo
     * Generate SEO title, meta description, and keyword list.
     */
    public function seo(Request $request): JsonResponse
    {
        $request->validate([
            'product'       => 'required|array',
            'product.title' => 'required|string|max:300',
        ]);

        return $this->runGeneration(fn() =>
            $this->ai->generateSeoMeta($request->input('product'))
        );
    }

    /**
     * POST /ior/ai/listing
     * Generate full marketplace listing: title + description + bullets + FAQs.
     */
    public function listing(Request $request): JsonResponse
    {
        $request->validate([
            'product'       => 'required|array',
            'product.title' => 'required|string|max:300',
            'lang'          => 'nullable|in:both,en,bn',
        ]);

        return $this->runGeneration(fn() =>
            $this->ai->generateListing($request->input('product'), $request->input('lang', 'both'))
        );
    }

    /**
     * POST /ior/ai/translate
     * Translate given text to Bangla.
     */
    public function translate(Request $request): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:3000']);

        return $this->runGeneration(fn() =>
            $this->ai->translateToBangla($request->input('text'))
        );
    }

    /**
     * POST /ior/ai/social
     * Generate social media caption (Facebook/Instagram).
     */
    public function social(Request $request): JsonResponse
    {
        $request->validate([
            'product'       => 'required|array',
            'product.title' => 'required|string',
            'platform'      => 'nullable|in:facebook,instagram,tiktok',
        ]);

        return $this->runGeneration(fn() =>
            $this->ai->generateSocialCaption($request->input('product'), $request->input('platform', 'facebook'))
        );
    }

    /**
     * POST /ior/ai/enrich-order/{id}
     * Generate + save AI description directly to an IOR order (product_description field).
     */
    public function enrichOrder(Request $request, int $id): JsonResponse
    {
        $request->validate(['lang' => 'nullable|in:both,en,bn']);

        $order = IorForeignOrder::findOrFail($id);

        // Build product array from order fields
        $product = [
            'title'       => $order->product_name,
            'source_url'  => $order->product_url,
            'price_bdt'   => $order->final_price_bdt ?? $order->estimated_price_bdt,
            'category'    => $order->product_category,
            'features'    => json_decode($order->product_features ?? '[]', true),
            'specifications' => json_decode($order->product_specs ?? '{}', true),
        ];

        try {
            if (!$this->ai->isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No AI provider configured. Go to IOR Settings → AI to add API keys.',
                ], 503);
            }

            $lang   = $request->input('lang', 'both');
            $result = $this->ai->generateDescription($product, $lang);

            // Persist to order
            $order->update(['ai_product_description' => $result['content']]);

            return response()->json([
                'success'   => true,
                'message'   => 'AI description generated and saved to order.',
                'provider'  => $result['provider'],
                'content'   => $result['content'],
                'order_id'  => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('[IOR AI] enrich-order error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IMAGE ANALYSIS  (POST /ior/ai/analyze-image)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /ior/ai/analyze-image
     * Analyse a product image with AI vision and return structured product data.
     *
     * Body: { image: "<base64 or URL>" }
     */
    public function analyzeImage(
        Request $request,
        \App\Modules\CrossBorderIOR\Services\ProductImageAnalysisService $imageAi
    ): JsonResponse {
        $data = $request->validate([
            'image' => 'required|string',
        ]);

        try {
            $result = $imageAi->analyse($data['image']);

            if (isset($result['_error'])) {
                return response()->json(['success' => false, 'message' => $result['_error']], 500);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('[IOR AI] analyzeImage error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BESTSELLERS  (GET /ior/ai/bestsellers)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /ior/ai/bestsellers
     * Fetch bestselling products from foreign marketplaces via Apify / Oxylabs.
     *
     * Query params: marketplace, category, search_query, custom_url, limit, offset
     */
    public function bestsellers(
        Request $request,
        \App\Modules\CrossBorderIOR\Services\BestsellersService $svc
    ): JsonResponse {
        $params = $request->validate([
            'marketplace'  => 'nullable|string|in:amazon,walmart,ebay,aliexpress,alibaba',
            'category'     => 'nullable|string|max:80',
            'search_query' => 'nullable|string|max:200',
            'custom_url'   => 'nullable|url',
            'limit'        => 'nullable|integer|min:1|max:100',
            'offset'       => 'nullable|integer|min:0',
        ]);

        $result = $svc->fetch(
            marketplace : $params['marketplace']  ?? 'amazon',
            category    : $params['category']     ?? null,
            searchQuery : $params['search_query'] ?? null,
            customUrl   : $params['custom_url']   ?? null,
            limit       : (int) ($params['limit']  ?? 20),
            offset      : (int) ($params['offset'] ?? 0),
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared handler
    // ──────────────────────────────────────────────────────────────────────────

    private function runGeneration(\Closure $fn): JsonResponse
    {
        if (!$this->ai->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'No AI provider configured. Go to IOR Settings → AI to add API keys (Gemini, OpenAI, or Claude).',
            ], 503);
        }

        try {
            $result = $fn();
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('[IOR AI] Generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}



