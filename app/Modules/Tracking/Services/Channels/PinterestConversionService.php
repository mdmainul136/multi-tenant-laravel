<?php

namespace App\Modules\Tracking\Services\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pinterest Conversions API — Partner-Grade Integration.
 *
 * Full compliance with Pinterest API v5:
 *   - Server-side event tracking
 *   - User data matching (em, ph, external_id, click_id)
 *   - Commerce/catalog events with item-level data
 *   - Event dedup via event_id
 *   - Batch support (up to 1000 events)
 *
 * Endpoint: POST https://api.pinterest.com/v5/ad_accounts/{ad_account_id}/events
 */
class PinterestConversionService
{
    private const API_URL = 'https://api.pinterest.com/v5/ad_accounts';
    private const MAX_BATCH = 1000;

    private const STANDARD_EVENTS = [
        'page_visit', 'view_category', 'search', 'add_to_cart',
        'checkout', 'signup', 'lead', 'watch_video', 'custom',
    ];

    /**
     * Send event to Pinterest Conversions API.
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
        $adAccountId = $creds['ad_account_id'] ?? null;
        $token       = $creds['access_token'] ?? null;

        if (!$adAccountId || !$token) {
            return ['success' => false, 'error' => 'Missing ad_account_id or access_token'];
        }

        $chunks = array_chunk($events, self::MAX_BATCH);
        $results = [];

        foreach ($chunks as $chunk) {
            $processedEvents = array_map(
                fn ($event) => $this->buildEvent($event),
                $chunk
            );

            $url = self::API_URL . "/{$adAccountId}/events";

            try {
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->post($url, ['data' => $processedEvents]);

                $results[] = [
                    'success'     => $response->successful(),
                    'status_code' => $response->status(),
                    'body'        => $response->json(),
                    'events_sent' => count($processedEvents),
                ];
            } catch (\Exception $e) {
                Log::error('[Pinterest CAPI] Request failed', ['error' => $e->getMessage()]);
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return count($results) === 1 ? $results[0] : ['batches' => $results];
    }

    /**
     * Build a Pinterest conversion event.
     */
    private function buildEvent(array $event): array
    {
        $pinEvent = [
            'event_name'       => $this->normalizeEventName($event['event_name'] ?? 'page_visit'),
            'action_source'    => $event['action_source'] ?? 'web',
            'event_time'       => $event['event_time'] ?? time(),
            'event_id'         => $event['event_id'] ?? (string) Str::uuid(),
        ];

        // Source URL
        if (!empty($event['event_source_url'])) {
            $pinEvent['event_source_url'] = $event['event_source_url'];
        }

        // ── User Data ──────────────────────────────────
        $userData = $event['user_data'] ?? [];
        $pinUser = [];

        // Hashed email (array of hashed emails)
        $email = $userData['em'] ?? $userData['email'] ?? null;
        if ($email) {
            $pinUser['em'] = [$this->ensureHashed($email)];
        }

        // Hashed phone
        $phone = $userData['ph'] ?? $userData['phone'] ?? null;
        if ($phone) {
            $pinUser['ph'] = [$this->ensureHashed(preg_replace('/[^\d]/', '', $phone))];
        }

        // External ID
        $extId = $userData['external_id'] ?? null;
        if ($extId) {
            $pinUser['external_id'] = [$this->ensureHashed($extId)];
        }

        // Name fields
        if (!empty($userData['fn'])) $pinUser['fn'] = [$this->ensureHashed($userData['fn'])];
        if (!empty($userData['ln'])) $pinUser['ln'] = [$this->ensureHashed($userData['ln'])];
        if (!empty($userData['ge'])) $pinUser['ge'] = [$this->ensureHashed($userData['ge'])];
        if (!empty($userData['db'])) $pinUser['db'] = [$this->ensureHashed($userData['db'])];
        if (!empty($userData['ct'])) $pinUser['ct'] = [$this->ensureHashed($userData['ct'])];
        if (!empty($userData['st'])) $pinUser['st'] = [$this->ensureHashed($userData['st'])];
        if (!empty($userData['zp'])) $pinUser['zp'] = [$this->ensureHashed($userData['zp'])];
        if (!empty($userData['country'])) $pinUser['country'] = [$this->ensureHashed($userData['country'])];

        // Client info (not hashed)
        $pinUser['client_ip_address'] = $userData['client_ip_address'] ?? request()?->ip() ?? '';
        $pinUser['client_user_agent'] = $userData['client_user_agent'] ?? request()?->userAgent() ?? '';

        // Pinterest Click ID
        $clickId = $event['epik'] ?? $userData['click_id'] ?? request()?->cookie('_epik') ?? request()?->get('epik');
        if ($clickId) {
            $pinUser['click_id'] = $clickId;
        }

        $pinEvent['user_data'] = $pinUser;

        // ── Custom Data ────────────────────────────────
        $customData = $event['custom_data'] ?? [];
        if (!empty($customData)) {
            $pinCustom = [];

            if (isset($customData['value'])) $pinCustom['value'] = (string) $customData['value'];
            $pinCustom['currency'] = $customData['currency'] ?? 'USD';
            if (!empty($customData['order_id'])) $pinCustom['order_id'] = $customData['order_id'];
            if (!empty($customData['num_items'])) $pinCustom['num_items'] = (int) $customData['num_items'];
            if (!empty($customData['search_string'])) $pinCustom['search_string'] = $customData['search_string'];
            if (!empty($customData['content_name'])) $pinCustom['content_name'] = $customData['content_name'];

            // Content IDs
            if (!empty($customData['content_ids'])) {
                $pinCustom['content_ids'] = $customData['content_ids'];
            }

            // Item-level content array
            if (!empty($customData['contents'])) {
                $pinCustom['contents'] = $customData['contents'];
            }

            $pinEvent['custom_data'] = $pinCustom;
        }

        return $pinEvent;
    }

    private function ensureHashed(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/', $value)) return $value;
        return hash('sha256', $value);
    }

    private function normalizeEventName(string $name): string
    {
        $map = [
            'purchase'          => 'checkout',
            'page_view'         => 'page_visit',
            'pageview'          => 'page_visit',
            'view_content'      => 'page_visit',
            'add_to_cart'       => 'add_to_cart',
            'addtocart'         => 'add_to_cart',
            'begin_checkout'    => 'checkout',
            'initiate_checkout' => 'checkout',
            'sign_up'           => 'signup',
            'register'          => 'signup',
            'search'            => 'search',
            'lead'              => 'lead',
            'view_category'     => 'view_category',
            'watch_video'       => 'watch_video',
        ];

        return $map[strtolower($name)] ?? 'custom';
    }
}
