<?php

namespace App\Modules\Tracking\Services\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google Ads Enhanced Conversions API — Partner-Grade Integration.
 *
 * Supports:
 *   - Offline conversion upload via ConversionUploadService REST API
 *   - Enhanced Conversions for Web (hashed PII matching)
 *   - Enhanced Conversions for Leads (hashed email/phone + GCLID)
 *   - GCLID / GBRAID / WBRAID click ID tracking
 *   - Conversion value, currency, order_id
 *   - Consent mode signals (ad_user_data, ad_personalization)
 *
 * Endpoint: POST https://googleads.googleapis.com/v17/customers/{customerId}:uploadClickConversions
 */
class GoogleAdsConversionService
{
    private const API_VERSION = 'v17';
    private const API_BASE = 'https://googleads.googleapis.com';

    /**
     * Upload a conversion to Google Ads.
     */
    public function sendEvent(array $event, array $creds, ?array $options = []): array
    {
        return $this->sendEvents([$event], $creds, $options);
    }

    /**
     * Upload batch conversions.
     */
    public function sendEvents(array $events, array $creds, ?array $options = []): array
    {
        $customerId        = $creds['customer_id'] ?? null;
        $conversionAction  = $creds['conversion_action'] ?? null;
        $accessToken       = $creds['access_token'] ?? null;
        $developerToken    = $creds['developer_token'] ?? null;

        if (!$customerId || !$accessToken || !$developerToken) {
            return ['success' => false, 'error' => 'Missing customer_id, access_token, or developer_token'];
        }

        // Remove hyphens from customer ID
        $customerId = str_replace('-', '', $customerId);

        $conversions = array_map(
            fn ($event) => $this->buildConversion($event, $customerId, $conversionAction),
            $events
        );

        $url = self::API_BASE . '/' . self::API_VERSION . "/customers/{$customerId}:uploadClickConversions";

        $payload = [
            'conversions'     => $conversions,
            'partialFailure'  => true,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization'   => "Bearer {$accessToken}",
                'developer-token' => $developerToken,
                'Content-Type'    => 'application/json',
            ])->timeout(30)->post($url, $payload);

            $body = $response->json();

            return [
                'success'       => $response->successful(),
                'status_code'   => $response->status(),
                'results'       => $body['results'] ?? [],
                'partial_failure_error' => $body['partialFailureError'] ?? null,
                'events_sent'   => count($conversions),
            ];
        } catch (\Exception $e) {
            Log::error('[Google Ads] Upload failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build a single Google Ads conversion object.
     */
    private function buildConversion(array $event, string $customerId, ?string $conversionAction): array
    {
        $conversion = [];

        // Conversion action resource name
        $actionId = $event['conversion_action_id'] ?? $conversionAction ?? '';
        if ($actionId && !str_starts_with($actionId, 'customers/')) {
            $conversion['conversionAction'] = "customers/{$customerId}/conversionActions/{$actionId}";
        } elseif ($actionId) {
            $conversion['conversionAction'] = $actionId;
        }

        // Click IDs (GCLID preferred, then GBRAID, WBRAID)
        $gclid  = $event['gclid']  ?? $event['user_data']['gclid'] ?? null;
        $gbraid = $event['gbraid'] ?? $event['user_data']['gbraid'] ?? null;
        $wbraid = $event['wbraid'] ?? $event['user_data']['wbraid'] ?? null;

        if ($gclid)  $conversion['gclid'] = $gclid;
        if ($gbraid) $conversion['gbraid'] = $gbraid;
        if ($wbraid) $conversion['wbraid'] = $wbraid;

        // Conversion date/time (ISO 8601 with timezone)
        $timestamp = $event['event_time'] ?? time();
        $conversion['conversionDateTime'] = date('Y-m-d H:i:sP', $timestamp);

        // Value and currency
        if (isset($event['custom_data']['value'])) {
            $conversion['conversionValue'] = (float) $event['custom_data']['value'];
        }
        $conversion['currencyCode'] = $event['custom_data']['currency'] ?? 'USD';

        // Order ID for deduplication
        if (!empty($event['custom_data']['order_id'])) {
            $conversion['orderId'] = $event['custom_data']['order_id'];
        } elseif (!empty($event['event_id'])) {
            $conversion['orderId'] = $event['event_id'];
        }

        // ── Enhanced Conversions (hashed PII) ─────────────
        $userData = $event['user_data'] ?? [];
        if (!empty($userData)) {
            $conversion['userIdentifiers'] = $this->buildUserIdentifiers($userData);
        }

        // ── Consent Signals ───────────────────────────────
        $consent = $event['consent'] ?? [];
        if (!empty($consent)) {
            $conversion['consent'] = [
                'adUserData'          => ($consent['ad_user_data'] ?? true) ? 'GRANTED' : 'DENIED',
                'adPersonalization'   => ($consent['ad_personalization'] ?? true) ? 'GRANTED' : 'DENIED',
            ];
        }

        return $conversion;
    }

    /**
     * Build user identifiers for Enhanced Conversions.
     */
    private function buildUserIdentifiers(array $userData): array
    {
        $identifiers = [];

        // Email (hashed)
        $email = $userData['em'] ?? $userData['email'] ?? null;
        if ($email) {
            $identifiers[] = [
                'hashedEmail' => $this->ensureHashed($email),
            ];
        }

        // Phone (hashed)
        $phone = $userData['ph'] ?? $userData['phone'] ?? null;
        if ($phone) {
            $identifiers[] = [
                'hashedPhoneNumber' => $this->ensureHashed($this->normalizePhone($phone)),
            ];
        }

        // Address info
        $hasAddress = !empty($userData['fn']) || !empty($userData['ln']);
        if ($hasAddress) {
            $address = [];
            if (!empty($userData['fn'])) $address['hashedFirstName'] = $this->ensureHashed($userData['fn']);
            if (!empty($userData['ln'])) $address['hashedLastName'] = $this->ensureHashed($userData['ln']);
            if (!empty($userData['ct'])) $address['city'] = strtolower(trim($userData['ct']));
            if (!empty($userData['st'])) $address['state'] = strtoupper(trim($userData['st']));
            if (!empty($userData['zp'])) $address['postalCode'] = trim($userData['zp']);
            if (!empty($userData['country'])) $address['countryCode'] = strtoupper(trim($userData['country']));

            $identifiers[] = ['addressInfo' => $address];
        }

        return $identifiers;
    }

    private function ensureHashed(string $value): string
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value)) {
            return $value;
        }
        return hash('sha256', strtolower(trim($value)));
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone);
    }
}
