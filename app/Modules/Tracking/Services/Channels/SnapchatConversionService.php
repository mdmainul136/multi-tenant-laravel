<?php

namespace App\Modules\Tracking\Services\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Snapchat Conversions API v2 — Partner-Grade Integration.
 *
 * Full compliance with Snap's CAPI v2:
 *   - All event types (WEB, MOBILE_APP, OFFLINE)
 *   - Rich user matching (hashed email, phone, IP, UA, SCID)
 *   - Event deduplication via event_tag + event_conversion_type
 *   - Item catalog support for commerce events
 *   - Click attribution via ScCid (Snap Click ID)
 *
 * Endpoint: POST https://tr.snapchat.com/v3/conversion
 */
class SnapchatConversionService
{
    private const API_URL = 'https://tr.snapchat.com/v3/conversion';

    private const HASH_FIELDS = ['hashed_email', 'hashed_phone_number', 'hashed_first_name_sha', 'hashed_last_name_sha', 'hashed_city_sha', 'hashed_state_sha', 'hashed_zip'];

    /**
     * Send event to Snapchat CAPI.
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
        $pixelId = $creds['pixel_id'] ?? null;
        $token   = $creds['access_token'] ?? null;

        if (!$pixelId || !$token) {
            return ['success' => false, 'error' => 'Missing pixel_id or access_token'];
        }

        $processedEvents = array_map(
            fn ($event) => $this->buildEvent($event, $pixelId),
            $events
        );

        // Snapchat accepts array of events
        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post(self::API_URL, $processedEvents);

            return [
                'success'     => $response->successful(),
                'status_code' => $response->status(),
                'body'        => $response->json(),
                'events_sent' => count($processedEvents),
            ];
        } catch (\Exception $e) {
            Log::error('[Snapchat CAPI] Request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build a Snapchat conversion event.
     */
    private function buildEvent(array $event, string $pixelId): array
    {
        $snapEvent = [
            'pixel_id'              => $pixelId,
            'event_type'            => $this->normalizeEventName($event['event_name'] ?? 'PAGE_VIEW'),
            'event_conversion_type' => $event['conversion_type'] ?? 'WEB',
            'timestamp'             => (string) (($event['event_time'] ?? time()) * 1000), // ms
        ];

        // Event tag for dedup
        if (!empty($event['event_id'])) {
            $snapEvent['event_tag'] = $event['event_id'];
        }

        // Page URL
        if (!empty($event['event_source_url'])) {
            $snapEvent['page_url'] = $event['event_source_url'];
        }

        // ── User Data ──────────────────────────────────
        $userData = $event['user_data'] ?? [];

        // Hashed email
        $email = $userData['em'] ?? $userData['email'] ?? null;
        if ($email) {
            $snapEvent['hashed_email'] = $this->ensureHashed($email);
        }

        // Hashed phone
        $phone = $userData['ph'] ?? $userData['phone'] ?? null;
        if ($phone) {
            $snapEvent['hashed_phone_number'] = $this->ensureHashed(preg_replace('/[^\d]/', '', $phone));
        }

        // Name fields
        if (!empty($userData['fn'])) $snapEvent['hashed_first_name_sha'] = $this->ensureHashed($userData['fn']);
        if (!empty($userData['ln'])) $snapEvent['hashed_last_name_sha'] = $this->ensureHashed($userData['ln']);
        if (!empty($userData['ct'])) $snapEvent['hashed_city_sha'] = $this->ensureHashed($userData['ct']);
        if (!empty($userData['st'])) $snapEvent['hashed_state_sha'] = $this->ensureHashed($userData['st']);
        if (!empty($userData['zp'])) $snapEvent['hashed_zip'] = $this->ensureHashed($userData['zp']);

        // IP + UA
        $snapEvent['hashed_ip_address'] = $this->ensureHashed($userData['client_ip_address'] ?? request()?->ip() ?? '');
        $snapEvent['user_agent'] = $userData['client_user_agent'] ?? request()?->userAgent() ?? '';

        // Snap Click ID (ScCid)
        $sccid = $event['sccid'] ?? $userData['sccid'] ?? request()?->cookie('_scid') ?? request()?->get('ScCid');
        if ($sccid) {
            $snapEvent['click_id'] = $sccid;
        }

        // ── Commerce Data ──────────────────────────────
        $customData = $event['custom_data'] ?? [];
        if (!empty($customData['value'])) {
            $snapEvent['price'] = (string) $customData['value'];
        }
        $snapEvent['currency'] = $customData['currency'] ?? 'USD';

        if (!empty($customData['num_items'])) {
            $snapEvent['number_items'] = (string) $customData['num_items'];
        }
        if (!empty($customData['order_id'])) {
            $snapEvent['transaction_id'] = $customData['order_id'];
        }
        if (!empty($customData['search_string'])) {
            $snapEvent['search_string'] = $customData['search_string'];
        }

        // Item catalog
        if (!empty($customData['content_ids'])) {
            $snapEvent['item_ids'] = $customData['content_ids'];
        }
        if (!empty($customData['content_category'])) {
            $snapEvent['item_category'] = $customData['content_category'];
        }

        return $snapEvent;
    }

    private function ensureHashed(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/', $value)) return $value;
        return hash('sha256', $value);
    }

    /**
     * Normalize event names to Snapchat format.
     */
    private function normalizeEventName(string $name): string
    {
        $map = [
            'purchase'         => 'PURCHASE',
            'add_to_cart'      => 'ADD_CART',
            'addtocart'        => 'ADD_CART',
            'page_view'        => 'PAGE_VIEW',
            'pageview'         => 'PAGE_VIEW',
            'view_content'     => 'VIEW_CONTENT',
            'viewcontent'      => 'VIEW_CONTENT',
            'sign_up'          => 'SIGN_UP',
            'register'         => 'SIGN_UP',
            'search'           => 'SEARCH',
            'add_to_wishlist'  => 'ADD_TO_WISHLIST',
            'begin_checkout'   => 'START_CHECKOUT',
            'initiate_checkout' => 'START_CHECKOUT',
            'add_payment_info' => 'ADD_BILLING',
            'lead'             => 'SIGN_UP',
            'subscribe'        => 'SUBSCRIBE',
            'complete_registration' => 'SIGN_UP',
        ];

        return $map[strtolower($name)] ?? strtoupper($name);
    }
}
