<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\CrossBorderIOR\IorSetting;
use App\Services\SaaSWalletService;

/**
 * AiContentService
 * Multi-provider AI for IOR product content generation.
 *
 * Supported providers (in priority order):
 *   1. Google Gemini     (gemini-1.5-pro / gemini-1.5-flash)
 *   2. OpenAI GPT-4o     (gpt-4o / gpt-4o-mini)
 *   3. Anthropic Claude  (claude-3-5-sonnet-20241022)
 *
 * Provider priority is determined by which API keys are set.
 * Keys stored in `ior_settings` table (or .env as fallback).
 *
 * All public methods accept the same `$product` array (from ApifyScraperService).
 */
class AiContentService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Config
    // ──────────────────────────────────────────────────────────────────────────

    private SaaSWalletService $walletService;

    public function __construct(SaaSWalletService $walletService)
    {
        $this->walletService     = $walletService;
    }

    private function getGeminiKey(): array { return $this->getSettingWithSource('gemini_api_key', env('GEMINI_API_KEY', '')); }
    private function getOpenaiKey(): array { return $this->getSettingWithSource('openai_api_key', env('OPENAI_API_KEY', '')); }
    private function getClaudeKey(): array { return $this->getSettingWithSource('claude_api_key', env('ANTHROPIC_API_KEY', '')); }
    private function getPreferredProvider(): string { return IorSetting::get('ai_preferred_provider', 'auto'); }

    /**
     * Internal helper to know if a setting came from the tenant's DB or fallback.
     */
    private function getSettingWithSource(string $key, mixed $default = null): array
    {
        $row = IorSetting::where('key', $key)->first();
        return [
            'value' => $row ? $row->value : $default,
            'is_tenant' => (bool)$row
        ];
    }

    /** Returns which provider is available, considering user preference */
    public function resolveProvider(): string
    {
        $preferredProvider = $this->getPreferredProvider();
        if ($preferredProvider !== 'auto') {
            if ($preferredProvider === 'gemini' && $this->getGeminiKey()['value']) return 'gemini';
            if ($preferredProvider === 'openai' && $this->getOpenaiKey()['value']) return 'openai';
            if ($preferredProvider === 'claude' && $this->getClaudeKey()['value']) return 'claude';
        }

        // Auto: first available key wins
        if ($this->getGeminiKey()['value']) return 'gemini';
        if ($this->getOpenaiKey()['value']) return 'openai';
        if ($this->getClaudeKey()['value']) return 'claude';

        return 'none';
    }

    public function isAvailable(): bool
    {
        return $this->resolveProvider() !== 'none';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PUBLIC GENERATION METHODS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Generate a compelling product description in Bangla + English.
     *
     * @param  array  $product  output from ApifyScraperService
     * @param  string $lang     'bn' | 'en' | 'both'
     */
    public function generateDescription(array $product, string $lang = 'both', string $tenantId = null): array
    {
        $prompt = $this->buildDescriptionPrompt($product, $lang);
        $text   = $this->generate($prompt, 1000, $tenantId);

        return [
            'success'     => true,
            'provider'    => $this->resolveProvider(),
            'type'        => 'description',
            'language'    => $lang,
            'content'     => $text,
            'product_name'=> $product['title'] ?? '',
        ];
    }

    /**
     * Generate SEO-optimized title and meta description.
     */
    public function generateSeoMeta(array $product, string $tenantId = null): array
    {
        $prompt = $this->buildSeoPrompt($product);
        $raw    = $this->generate($prompt, 500, $tenantId);
        $parsed = $this->parseSeoJson($raw);

        return [
            'success'          => true,
            'provider'         => $this->resolveProvider(),
            'type'             => 'seo_meta',
            'seo_title'        => $parsed['seo_title']        ?? '',
            'meta_description' => $parsed['meta_description'] ?? '',
            'keywords'         => $parsed['keywords']         ?? [],
            'raw'              => $raw,
        ];
    }

    /**
     * Generate a full marketplace listing: title + description + bullet features + FAQs.
     */
    public function generateListing(array $product, string $lang = 'both', string $tenantId = null): array
    {
        $prompt = $this->buildListingPrompt($product, $lang);
        $raw    = $this->generate($prompt, 2000, $tenantId);

        return [
            'success'  => true,
            'provider' => $this->resolveProvider(),
            'type'     => 'full_listing',
            'language' => $lang,
            'content'  => $raw,
        ];
    }

    /**
     * Translate existing text to Bangla (for product titles/descriptions sourced in English).
     */
    public function translateToBangla(string $text, string $tenantId = null): array
    {
        $prompt = "Translate the following product text naturally to Bangla. Keep brand names, model numbers, and technical specs in English where appropriate. Maintain a professional tone suitable for e-commerce.\n\nText:\n{$text}";
        $translation = $this->generate($prompt, 1000, $tenantId);

        return [
            'success'     => true,
            'provider'    => $this->resolveProvider(),
            'type'        => 'translation',
            'original'    => $text,
            'translation' => $translation,
        ];
    }

    /**
     * Generate a short social media caption (Facebook/Instagram) for the product.
     */
    public function generateSocialCaption(array $product, string $platform = 'facebook'): array
    {
        $name  = $product['title']    ?? 'this product';
        $price = $product['price_bdt'] ?? null;

        $priceStr = $price ? "Price: ৳" . number_format($price) . " BDT" : '';
        $prompt   = <<<PROMPT
Write a short, engaging {$platform} post in a mix of Bangla and English for this product:

Product: {$name}
{$priceStr}
Source: {$product['source_url']}

Requirements:
- 2-3 sentences max
- Exciting, persuasive tone
- Include 3-5 relevant emojis
- Add 5 relevant hashtags at the end
- Mention "Cross-Border IOR" or "বিদেশ থেকে আনা" naturally
PROMPT;

        return [
            'success'  => true,
            'provider' => $this->resolveProvider(),
            'type'     => "social_{$platform}",
            'platform' => $platform,
            'content'  => $this->generate($prompt, 500, $product['tenant_id'] ?? null),
        ];
    }

    /**
     * Infer HS codes from product title/description.
     */
    public function inferHsCode(string $title, string $description, string $country = 'BD', string $tenantId = null): array
    {
        $prompt = <<<PROMPT
You are a customs compliance AI expert specializing in the Harmonized System (HS) for international trade.
Analyze the following product for import into {$country}:

Title: {$title}
Description: {$description}

Return ONLY a JSON array of 3-5 potential HS codes with confidence scores and logic:
[
  {
    "hs_code": "8471.30",
    "category": "Portable digital automatic data processing machines",
    "confidence": 0.95,
    "logic": "Matches laptop computers weighing <= 10kg."
  }
]
PROMPT;

        $raw = $this->generate($prompt, 1000, $tenantId);
        $parsed = json_decode($this->cleanJson($raw), true);

        return [
            'success'  => true,
            'provider' => $this->resolveProvider(),
            'type'     => 'hs_inference',
            'data'     => is_array($parsed) ? $parsed : [],
            'raw'      => $raw
        ];
    }

    private function cleanJson(string $raw): string
    {
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);
        return trim($cleaned);
    }

    private function buildDescriptionPrompt(array $p, string $lang): string
    {
        $name     = $p['title']           ?? 'the product';
        $features = implode(', ', array_slice($p['features'] ?? [], 0, 8));
        $category = $p['category']        ?? 'Electronics';
        $price    = isset($p['price_bdt']) ? '৳' . number_format($p['price_bdt']) . ' BDT' : '';
        $specs    = isset($p['specifications']) ? json_encode($p['specifications'], JSON_UNESCAPED_UNICODE) : '';

        $langInstruction = match ($lang) {
            'bn'   => "Write ONLY in Bangla (বাংলা).",
            'en'   => "Write ONLY in English.",
            default => "Write first in English, then provide a Bangla translation below it separated by '---BANGLA---'.",
        };

        return <<<PROMPT
You are a professional e-commerce copywriter for a cross-border import (IOR) service in Bangladesh.

Product: {$name}
Category: {$category}
Key Features: {$features}
Price: {$price}
Specifications: {$specs}

{$langInstruction}

Write a compelling product description that:
1. Starts with an engaging hook sentence
2. Highlights the top 4-5 benefits (not just features)
3. Explains WHY a Bangladeshi customer should buy this
4. Mentions international quality/authenticity
5. Ends with a gentle call-to-action
6. 150-250 words per language
7. Use natural, conversational tone — not robotic
8. Do NOT mention specific delivery dates or fake guarantees
PROMPT;
    }

    private function buildSeoPrompt(array $p): string
    {
        $name     = $p['title']    ?? '';
        $category = $p['category'] ?? '';
        $features = implode(', ', array_slice($p['features'] ?? [], 0, 5));

        return <<<PROMPT
You are an SEO expert for a Bangladeshi e-commerce cross-border import store.

Product: {$name}
Category: {$category}
Features: {$features}

Generate SEO metadata and return ONLY valid JSON (no markdown, no explanation):
{
  "seo_title": "...",        // 50-60 chars, includes main keyword, brand if known
  "meta_description": "...", // 150-160 chars, compelling, includes CTA
  "keywords": ["...", "..."] // array of 8-12 keywords (mix of English and Bangla transliterations)
}
PROMPT;
    }

    private function buildListingPrompt(array $p, string $lang): string
    {
        $name     = $p['title']    ?? 'the product';
        $category = $p['category'] ?? 'General';
        $features = implode("\n- ", array_slice($p['features'] ?? [], 0, 10));
        $price    = isset($p['price_bdt']) ? '৳' . number_format($p['price_bdt']) : 'N/A';

        $langInstruction = match ($lang) {
            'bn'   => "Write everything in Bangla.",
            'en'   => "Write everything in English.",
            default => "Write each section in English, then Bangla translation below it.",
        };

        return <<<PROMPT
Create a complete marketplace listing for this cross-border import product:

Product: {$name}
Category: {$category}
Price: {$price}
Features:
- {$features}

{$langInstruction}

Provide the following sections:
## TITLE
(optimized marketplace title, 80-100 chars)

## SHORT DESCRIPTION
(2-3 sentence elevator pitch)

## FULL DESCRIPTION
(detailed, 200-300 words)

## BULLET POINTS
- (5-7 concise feature/benefit bullets)

## FAQ
Q: Is this an original product?
A: ...
Q: What is the delivery time?
A: ...
Q: Can I return the product?
A: ...

Keep the tone professional yet friendly. Target audience: Bangladeshi online shoppers.
PROMPT;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PROVIDER DISPATCH
    // ══════════════════════════════════════════════════════════════════════════

    private function generate(string $prompt, int $maxTokens = 1000, string $tenantId = null): string
    {
        $provider = $this->resolveProvider();

        if ($provider === 'none') {
            throw new \RuntimeException(
                'No AI provider configured. Set GEMINI_API_KEY, OPENAI_API_KEY, or ANTHROPIC_API_KEY in .env or IOR Settings.'
            );
        }

        // Resolve active key info
        $keyInfo = match ($provider) {
            'gemini' => $this->getGeminiKey(),
            'openai' => $this->getOpenaiKey(),
            'claude' => $this->getClaudeKey(),
        };

        // Charge the wallet ONLY IF using Landlord/Platform keys (not tenant's own keys)
        if ($tenantId && !$keyInfo['is_tenant']) {
            $cost = (float) IorSetting::get('ai_cost_per_request', 0.05);
            $charged = $this->walletService->debit(
                $tenantId, 
                $cost, 
                'ai', 
                "AI generation using Platform Fallback ({$provider})", 
                md5($prompt)
            );

            if (!$charged) {
                throw new \RuntimeException("Insufficient SaaS wallet balance for AI generation (using platform keys).");
            }
        }

        // Tier Quota Increment (for all requests, even if tenant keys are used)
        if ($tenantId) {
            $prefix = config('ior_quotas.redis_prefix', 'ior_quota:');
            $date = date('Y-m-d');
            $key = "{$prefix}{$tenantId}:ai:{$date}";

            Cache::increment($key);
            if (!Cache::has($key . ':expiry')) {
                Cache::put($key . ':expiry', true, 86400 * 2);
            }
        }

        Log::info("[IOR AI] Generating with provider: $provider (Source: " . ($keyInfo['is_tenant'] ? 'Tenant' : 'Platform') . ")");

        return match ($provider) {
            'gemini' => $this->callGemini($prompt, $maxTokens, $keyInfo['value']),
            'openai' => $this->callOpenAI($prompt, $maxTokens, $keyInfo['value']),
            'claude' => $this->callClaude($prompt, $maxTokens, $keyInfo['value']),
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Google Gemini
    // ──────────────────────────────────────────────────────────────────────────

    private function callGemini(string $prompt, int $maxTokens, string $apiKey): string
    {
        $model = IorSetting::get('gemini_model', 'gemini-1.5-flash');
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(30)->post($url, [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature'     => 0.7,
                'topP'            => 0.9,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
            ],
        ]);

        if ($response->failed()) {
            $err = $response->json('error.message', $response->body());
            throw new \RuntimeException("Gemini API error: $err");
        }

        return $response->json('candidates.0.content.parts.0.text')
            ?? throw new \RuntimeException('Gemini returned empty content.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // OpenAI GPT
    // ──────────────────────────────────────────────────────────────────────────

    private function callOpenAI(string $prompt, int $maxTokens, string $apiKey): string
    {
        $model    = IorSetting::get('openai_model', 'gpt-4o-mini');
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'temperature'=> 0.7,
                'messages'   => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a professional e-commerce copywriter specializing in cross-border import products for the Bangladeshi market.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            $err = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI API error: $err");
        }

        return $response->json('choices.0.message.content')
            ?? throw new \RuntimeException('OpenAI returned empty content.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Anthropic Claude
    // ──────────────────────────────────────────────────────────────────────────

    private function callClaude(string $prompt, int $maxTokens, string $apiKey): string
    {
        $model    = IorSetting::get('claude_model', 'claude-3-5-sonnet-20241022');
        $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'system'     => 'You are a professional e-commerce copywriter specializing in cross-border import products for the Bangladeshi market.',
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            $err = $response->json('error.message', $response->body());
            throw new \RuntimeException("Claude API error: $err");
        }

        return $response->json('content.0.text')
            ?? throw new \RuntimeException('Claude returned empty content.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function parseSeoJson(string $raw): array
    {
        // Strip markdown code fences if model wrapped in ```json ... ```
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);
        $decoded = json_decode(trim($cleaned), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function listModels(): array
    {
        return [
            'gemini' => [
                'available' => (bool) $this->getGeminiKey()['value'],
                'models'    => ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.0-flash-exp'],
                'current'   => IorSetting::get('gemini_model', 'gemini-1.5-flash'),
            ],
            'openai' => [
                'available' => (bool) $this->getOpenaiKey()['value'],
                'models'    => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
                'current'   => IorSetting::get('openai_model', 'gpt-4o-mini'),
            ],
            'claude' => [
                'available' => (bool) $this->getClaudeKey()['value'],
                'models'    => ['claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307', 'claude-3-opus-20240229'],
                'current'   => IorSetting::get('claude_model', 'claude-3-5-sonnet-20241022'),
            ],
            'preferred' => $this->getPreferredProvider(),
            'active'    => $this->resolveProvider(),
        ];
    }
}



