<?php

namespace App\Modules\Tracking\Services\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LinkedIn Conversions API — Partner-Grade Integration.
 *
 * Full compliance with LinkedIn Marketing API:
 *   - Server-side conversion tracking
 *   - User matching via hashed email, LinkedIn first-party cookies (li_fat_id)
 *   - Conversion rules with monetary values
 *   - Event dedup via conversionHappenedAt + user
 *
 * Endpoint: POST https://api.linkedin.com/rest/conversionEvents
 */
class LinkedInConversionService
{
    private const API_URL = 'https://api.linkedin.com/rest/conversionEvents';
    private const API_VERSION = '202402';

    /**
     * Send conversion event to LinkedIn.
     */
    public function sendEvent(array $event, array $creds, ?array $options = []): array
    {
        return $this->sendEvents([$event], $creds, $options);
    }

    /**
     * Send batch conversion events.
     */
    public function sendEvents(array $events, array $creds, ?array $options = []): array
    {
        $token          = $creds['access_token'] ?? null;
        $conversionRule = $creds['conversion_rule_id'] ?? null;
        $adAccountId    = $creds['ad_account_id'] ?? null;

        if (!$token) {
            return ['success' => false, 'error' => 'Missing access_token'];
        }

        $elements = array_map(
            fn ($event) => $this->buildConversion($event, $conversionRule, $adAccountId),
            $events
        );

        $payload = [
            'elements' => $elements,
        ];

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'LinkedIn-Version' => self::API_VERSION,
                    'Content-Type'     => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->timeout(30)
                ->post(self::API_URL, $payload);

            return [
                'success'     => $response->successful(),
                'status_code' => $response->status(),
                'body'        => $response->json(),
                'events_sent' => count($elements),
            ];
        } catch (\Exception $e) {
            Log::error('[LinkedIn CAPI] Request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build a LinkedIn conversion event.
     */
    private function buildConversion(array $event, ?string $conversionRule, ?string $adAccountId): array
    {
        $conversion = [
            'conversion' => $conversionRule
                ? "urn:lla:llaPartnerConversion:{$conversionRule}"
                : "urn:lla:llaPartnerConversion:" . ($event['conversion_rule_id'] ?? ''),
            'conversionHappenedAt' => ($event['event_time'] ?? time()) * 1000, // ms
            'eventId'              => $event['event_id'] ?? (string) Str::uuid(),
        ];

        // Conversion value
        if (isset($event['custom_data']['value'])) {
            $conversion['conversionValue'] = [
                'currencyCode' => $event['custom_data']['currency'] ?? 'USD',
                'amount'       => (string) $event['custom_data']['value'],
            ];
        }

        // ── User Identity ──────────────────────────────
        $userData = $event['user_data'] ?? [];
        $userIds = [];

        // Hashed email (SHA-256, lowercase, trimmed)
        $email = $userData['em'] ?? $userData['email'] ?? null;
        if ($email) {
            $userIds[] = [
                'userIdType'    => 'SHA256_EMAIL',
                'idValue'       => $this->ensureHashed($email),
            ];
        }

        // LinkedIn first-party ID (li_fat_id cookie)
        $liFatId = $event['li_fat_id'] ?? $userData['li_fat_id'] ?? request()?->cookie('li_fat_id');
        if ($liFatId) {
            $userIds[] = [
                'userIdType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID',
                'idValue'    => $liFatId,
            ];
        }

        // Acxiom ID
        if (!empty($userData['acxiom_id'])) {
            $userIds[] = [
                'userIdType' => 'ACXIOM_ID',
                'idValue'    => $userData['acxiom_id'],
            ];
        }

        // Oracle MOAT ID
        if (!empty($userData['oracle_moat_id'])) {
            $userIds[] = [
                'userIdType' => 'ORACLE_MOAT_ID',
                'idValue'    => $userData['oracle_moat_id'],
            ];
        }

        if (!empty($userIds)) {
            $conversion['user'] = [
                'userIds' => $userIds,
                'userInfo' => [
                    'firstName' => $userData['fn'] ?? $userData['first_name'] ?? '',
                    'lastName'  => $userData['ln'] ?? $userData['last_name'] ?? '',
                    'companyName' => $userData['company'] ?? '',
                    'title'     => $userData['job_title'] ?? '',
                    'countryCode' => $userData['country'] ?? '',
                ],
            ];
        }

        return $conversion;
    }

    private function ensureHashed(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/', $value)) return $value;
        return hash('sha256', $value);
    }
}
