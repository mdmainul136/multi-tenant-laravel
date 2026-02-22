<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProductImageAnalysisService
 *
 * Sends a product image (base64 or URL) to an AI vision model
 * and returns structured product identification data.
 *
 * Ported from Supabase `analyze-product-image`.
 *
 * Supports three providers configured in IOR Settings:
 *   - openai  → GPT-4o (vision)
 *   - google  → Gemini 2.0 Flash
 *   - gemini  → (alias for google)
 *
 * Response shape:
 * {
 *   productName, category, brand, features[], searchKeywords[], searchQuery
 * }
 */
class ProductImageAnalysisService
{
    private const SYSTEM_PROMPT = <<<PROMPT
You are a product identification assistant. Analyze the product image and extract:
1. Product name/title (be specific, e.g., "Nike Air Jordan 1 High OG")
2. Category (e.g., shoes, electronics, clothing, accessories)
3. Brand (if identifiable)
4. Key features or characteristics
5. Search keywords (5-10 relevant terms for finding similar products)

Return ONLY a JSON object in this exact format:
{
  "productName": "string",
  "category": "string",
  "brand": "string or null",
  "features": ["array", "of", "features"],
  "searchKeywords": ["array", "of", "keywords"],
  "searchQuery": "optimized search query string"
}
PROMPT;

    // ──────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────

    /**
     * Analyse a product image.
     *
     * @param  string $imageInput Base64 string (with or without data-URI prefix) OR a public URL
     * @return array
     */
    public function analyse(string $imageInput): array
    {
        $provider = IorSetting::get('ai_provider', 'openai');
        $model    = IorSetting::get('default_ai_model', null);

        try {
            $raw = match (strtolower($provider)) {
                'google', 'gemini' => $this->callGoogle($imageInput, $model ?? 'gemini-2.0-flash'),
                default            => $this->callOpenAI($imageInput, $model ?? 'gpt-4o'),
            };

            return $this->parseResponse($raw);
        } catch (\Exception $e) {
            Log::error('[IOR ImageAnalysis] ' . $e->getMessage());
            return $this->fallback($e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // PROVIDERS
    // ──────────────────────────────────────────────────────────────

    private function callOpenAI(string $imageInput, string $model): string
    {
        $apiKey = IorSetting::get('openai_api_key')
            ?? config('services.openai.api_key')
            ?? env('OPENAI_API_KEY');

        if (!$apiKey) {
            throw new \RuntimeException('OpenAI API key not configured. Set openai_api_key in IOR Settings.');
        }

        $imageContent = str_starts_with($imageInput, 'http')
            ? ['type' => 'image_url', 'image_url' => ['url' => $imageInput, 'detail' => 'high']]
            : ['type' => 'image_url', 'image_url' => ['url' => $this->toDataUri($imageInput)]];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => 1000,
                'messages'   => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => [
                        ['type' => 'text', 'text' => 'Analyze this product image and extract the product details for search purposes.'],
                        $imageContent,
                    ]],
                ],
            ]);

        if ($response->status() === 429) {
            throw new \RuntimeException('OpenAI rate limit exceeded. Please try again later.');
        }
        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status());
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    private function callGoogle(string $imageInput, string $model): string
    {
        $apiKey = IorSetting::get('google_api_key')
            ?? config('services.google.api_key')
            ?? env('GOOGLE_AI_API_KEY');

        if (!$apiKey) {
            throw new \RuntimeException('Google AI API key not configured. Set google_api_key in IOR Settings.');
        }

        if (str_starts_with($imageInput, 'http')) {
            // For URL: use fileData
            $parts = [
                ['text' => self::SYSTEM_PROMPT . "\n\nAnalyze this product image."],
                ['fileData' => ['mimeType' => 'image/jpeg', 'fileUri' => $imageInput]],
            ];
        } else {
            $base64 = str_starts_with($imageInput, 'data:')
                ? explode(',', $imageInput)[1]
                : $imageInput;

            $parts = [
                ['text' => self::SYSTEM_PROMPT . "\n\nAnalyze this product image."],
                ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $base64]],
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(30)->post($url, [
            'contents'        => [['parts' => $parts]],
            'generationConfig'=> ['maxOutputTokens' => 1000],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google AI API error: ' . $response->status());
        }

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }

    // ──────────────────────────────────────────────────────────────
    // PARSING
    // ──────────────────────────────────────────────────────────────

    private function parseResponse(string $content): array
    {
        // Extract JSON block from AI response
        if (preg_match('/\{[\s\S]*\}/u', $content, $match)) {
            try {
                $parsed = json_decode($match[0], true, 512, JSON_THROW_ON_ERROR);
                return array_merge($this->emptyResult(), $parsed);
            } catch (\JsonException) {
                // Fall through
            }
        }

        // Couldn't parse — return partial result
        return array_merge($this->emptyResult(), [
            'searchQuery' => mb_substr($content, 0, 100),
        ]);
    }

    private function emptyResult(): array
    {
        return [
            'productName'    => 'Unknown Product',
            'category'       => 'General',
            'brand'          => null,
            'features'       => [],
            'searchKeywords' => [],
            'searchQuery'    => '',
        ];
    }

    private function fallback(string $error): array
    {
        return array_merge($this->emptyResult(), ['_error' => $error]);
    }

    private function toDataUri(string $base64): string
    {
        return str_starts_with($base64, 'data:')
            ? $base64
            : "data:image/jpeg;base64,{$base64}";
    }
}



