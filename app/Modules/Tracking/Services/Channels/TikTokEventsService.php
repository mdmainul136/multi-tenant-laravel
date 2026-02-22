<?php

namespace App\Modules\Tracking\Services\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TikTok Events API v2 — Partner-Grade Integration.
 *
 * Full compliance with TikTok's Business API:
 *   - Events API v2 endpoint with batch support
 *   - Complete user_data (email, phone, ttclid, external_id, IP, UA)
 *   - Auto SHA-256 hashing (TikTok requires pre-hashed data)
 *   - Event name normalization to TikTok standard events
 *   - Deduplication via event_id
 *   - Content parameters for catalog/commerce events
 *   - ttclid (TikTok Click ID) auto-detection from cookies
 *
 * Endpoint: POST https://business-api.tiktok.com/open_api/v1.3/event/track/
 */
class TikTokEventsService
{
    private const API_URL = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    private const STANDARD_EVENTS = [
        'ViewContent', 'ClickButton', 'Search', 'AddToWishlist',
        'AddToCart', 'InitiateCheckout', 'AddPaymentInfo', 'CompletePayment',
        'PlaceAnOrder', 'Contact', 'Download', 'SubmitForm',
        'CompleteRegistration', 'Subscribe',
    ];

    private const HASH_FIELDS = ['email', 'phone_number', 'external_id'];

    /**
     * Send event(s) to TikTok Events API.
     */
    public function sendEvent(array $event, array $creds, ?array $options = []): array
    {
        return $this->sendEvents([$event], $creds, $options);
    }

    /**
     * Send batch events.
     */
    public function sendEvents(array $events, array $creds, ?array $options = []): array
    {
        $pixelCode   = $creds['pixel_code'] ?? null;
        $accessToken = $creds['access_token'] ?? null;

        if (!$pixelCode || !$accessToken) {
            return ['success' => false, 'error' => 'Missing pixel_code or access_token'];
        }

        $processedEvents = array_map(
            fn ($event) => $this->buildEvent($event, $pixelCode),
            $events
        );

        $payload = [
            'event_source'    => 'web',
            'event_source_id' => $pixelCode,
            'data'            => $processedEvents,
        ];

        // Test event code for debugging
        if (!empty($options['test_event_code'])) {
            $payload['test_event_code'] = $options['test_event_code'];
        }

        try {
            $response = Http::withHeaders([
                'Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(self::API_URL, $payload);

            $body = $response->json();

            return [
                'success'     => $response->successful() && ($body['code'] ?? -1) === 0,
                'status_code' => $response->status(),
                'code'        => $body['code'] ?? null,
                'message'     => $body['message'] ?? '',
                'events_sent' => count($processedEvents),
            ];
        } catch (\Exception $e) {
            Log::error('[TikTok Events] Request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build a single TikTok server event.
     */
    private function buildEvent(array $event, string $pixelCode): array
    {
        $tiktokEvent = [
            'event'      => $this->normalizeEventName($event['event_name'] ?? 'ViewContent'),
            'event_time' => $event['event_time'] ?? time(),
            'event_id'   => $event['event_id'] ?? (string) Str::uuid(),
        ];

        // Page info
        if (!empty($event['event_source_url'])) {
            $tiktokEvent['page'] = [
                'url'      => $event['event_source_url'],
                'referrer' => $event['referrer'] ?? request()?->header('Referer') ?? '',
            ];
        }

        // ── User Data ──────────────────────────────────
        $tiktokEvent['user'] = $this->buildUserData($event);

        // ── Properties (custom_data equivalent) ────────
        if (!empty($event['custom_data'])) {
            $tiktokEvent['properties'] = $this->buildProperties($event['custom_data']);
        }

        return $tiktokEvent;
    }

    /**
     * Build user data with TikTok-required hashing.
     */
    private function buildUserData(array $event): array
    {
        $raw = $event['user_data'] ?? [];
        $user = [];

        // Map common field names
        $aliases = [
            'em' => 'email', 'ph' => 'phone_number',
            'email' => 'email', 'phone' => 'phone_number',
            'external_id' => 'external_id', 'ip' => 'ip',
            'client_ip_address' => 'ip', 'source_ip' => 'ip',
            'client_user_agent' => 'user_agent', 'user_agent' => 'user_agent',
        ];

        foreach ($raw as $key => $value) {
            $mapped = $aliases[$key] ?? $key;
            $user[$mapped] = $value;
        }

        // Auto-inject IP and UA
        if (empty($user['ip'])) {
            $user['ip'] = request()?->ip() ?? '';
        }
        if (empty($user['user_agent'])) {
            $user['user_agent'] = request()?->userAgent() ?? '';
        }

        // Auto-detect ttclid from cookie
        if (empty($user['ttclid'])) {
            $ttclid = $event['ttclid'] ?? request()?->cookie('ttclid') ?? request()?->get('ttclid');
            if ($ttclid) {
                $user['ttclid'] = $ttclid;
            }
        }

        // Hash PII fields
        foreach (self::HASH_FIELDS as $field) {
            if (isset($user[$field]) && !$this->isHashed($user[$field])) {
                $user[$field] = $this->hashValue($user[$field], $field);
            }
        }

        return $user;
    }

    /**
     * Build properties (commerce/content data).
     */
    private function buildProperties(array $customData): array
    {
        $props = [];

        $map = [
            'value'          => 'value',
            'currency'       => 'currency',
            'content_type'   => 'content_type',
            'content_ids'    => 'content_id',
            'content_name'   => 'content_name',
            'content_category' => 'content_category',
            'num_items'      => 'num_items',
            'order_id'       => 'order_id',
            'search_string'  => 'query',
            'description'    => 'description',
        ];

        foreach ($map as $from => $to) {
            if (isset($customData[$from])) {
                $props[$to] = $customData[$from];
            }
        }

        // Contents array (catalog items)
        if (!empty($customData['contents'])) {
            $props['contents'] = $customData['contents'];
        }

        return $props;
    }

    private function hashValue(string $value, string $field): string
    {
        $value = trim(strtolower($value));
        if ($field === 'phone_number') {
            $value = preg_replace('/[^\d]/', '', $value);
        }
        return hash('sha256', $value);
    }

    private function isHashed(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $value);
    }

    /**
     * Normalize event names to TikTok standards.
     */
    private function normalizeEventName(string $name): string
    {
        $map = [
            'purchase'         => 'CompletePayment',
            'completepayment'  => 'CompletePayment',
            'pageview'         => 'ViewContent',
            'page_view'        => 'ViewContent',
            'view_content'     => 'ViewContent',
            'add_to_cart'      => 'AddToCart',
            'addtocart'        => 'AddToCart',
            'begin_checkout'   => 'InitiateCheckout',
            'initiate_checkout' => 'InitiateCheckout',
            'add_payment_info' => 'AddPaymentInfo',
            'sign_up'          => 'CompleteRegistration',
            'register'         => 'CompleteRegistration',
            'search'           => 'Search',
            'add_to_wishlist'  => 'AddToWishlist',
            'lead'             => 'SubmitForm',
            'contact'          => 'Contact',
            'subscribe'        => 'Subscribe',
            'download'         => 'Download',
        ];

        return $map[strtolower($name)] ?? $name;
    }
}
