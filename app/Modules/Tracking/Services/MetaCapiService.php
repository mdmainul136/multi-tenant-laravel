<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Meta Conversions API (CAPI) Gateway Service.
 *
 * Production-grade implementation of Facebook's CAPI v21.0.
 * Handles:
 *   - Full user_data fields with auto SHA-256 hashing
 *   - All action_source types (website, app, email, phone_call, chat, physical_store)
 *   - event_source_url auto-detection
 *   - Auto event_id generation for Pixel <-> CAPI deduplication
 *   - Batch event support (up to 1000 events per API call)
 *   - Data processing options for CCPA/LDU compliance
 *   - Event Match Quality (EMQ) scoring
 *   - Test event code support for debugging
 */
class MetaCapiService
{
    private const API_VERSION = 'v21.0';
    private const MAX_BATCH_SIZE = 1000;
    private const GRAPH_API_BASE = 'https://graph.facebook.com';

    // Fields that MUST be SHA-256 hashed before sending
    private const HASH_FIELDS = [
        'em', 'ph', 'fn', 'ln', 'ge', 'db', 'ct', 'st', 'zp', 'country', 'external_id',
    ];

    // Fields that must NOT be hashed
    private const NO_HASH_FIELDS = [
        'client_ip_address', 'client_user_agent', 'fbc', 'fbp',
    ];

    // All valid standard event names recognized by Meta
    private const STANDARD_EVENTS = [
        'AddPaymentInfo', 'AddToCart', 'AddToWishlist', 'CompleteRegistration',
        'Contact', 'CustomizeProduct', 'Donate', 'FindLocation',
        'InitiateCheckout', 'Lead', 'PageView', 'Purchase',
        'Schedule', 'Search', 'StartTrial', 'SubmitApplication',
        'Subscribe', 'ViewContent',
    ];

    // Valid action_source values
    private const ACTION_SOURCES = [
        'website', 'app', 'email', 'phone_call', 'chat',
        'physical_store', 'system_generated', 'business_messaging', 'other',
    ];

    /**
     * Send a single event to Meta CAPI.
     */
    public function sendEvent(array $event, array $creds, ?array $options = []): array
    {
        return $this->sendEvents([$event], $creds, $options);
    }

    /**
     * Send batch events to Meta CAPI (up to 1000 per call).
     */
    public function sendEvents(array $events, array $creds, ?array $options = []): array
    {
        $pixelId = $creds['pixel_id'] ?? $creds['dataset_id'] ?? null;
        $token   = $creds['access_token'] ?? null;

        if (!$pixelId || !$token) {
            Log::warning('[Meta CAPI] Missing pixel_id or access_token');
            return ['success' => false, 'error' => 'Missing credentials'];
        }

        // Chunk into batches of 1000
        $chunks = array_chunk($events, self::MAX_BATCH_SIZE);
        $results = [];

        foreach ($chunks as $chunk) {
            $processedEvents = array_map(
                fn ($event) => $this->buildServerEvent($event, $options),
                $chunk
            );

            $payload = [
                'data' => $processedEvents,
            ];

            // Test event code for debugging (Meta Events Manager → Test Events)
            if (!empty($options['test_event_code'])) {
                $payload['test_event_code'] = $options['test_event_code'];
            }

            $url = self::GRAPH_API_BASE . '/' . self::API_VERSION . "/{$pixelId}/events";

            try {
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->post($url, $payload);

                $body = $response->json();

                $results[] = [
                    'success'       => $response->successful(),
                    'status_code'   => $response->status(),
                    'events_sent'   => count($processedEvents),
                    'events_received' => $body['events_received'] ?? 0,
                    'messages'      => $body['messages'] ?? [],
                    'fbtrace_id'    => $body['fbtrace_id'] ?? null,
                ];

                if (!$response->successful()) {
                    Log::error('[Meta CAPI] API Error', [
                        'status'   => $response->status(),
                        'body'     => $body,
                        'pixel_id' => $pixelId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('[Meta CAPI] Request failed', ['error' => $e->getMessage()]);
                $results[] = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return count($results) === 1 ? $results[0] : ['batches' => $results];
    }

    /**
     * Build a fully compliant Meta CAPI server event.
     */
    private function buildServerEvent(array $event, array $options = []): array
    {
        $serverEvent = [
            'event_name'    => $this->normalizeEventName($event['event_name'] ?? 'PageView'),
            'event_time'    => $event['event_time'] ?? time(),
            'action_source' => $this->validateActionSource($event['action_source'] ?? 'website'),
        ];

        // Event ID — critical for Pixel <-> CAPI deduplication
        $serverEvent['event_id'] = $event['event_id'] ?? (string) Str::uuid();

        // Event source URL (required for website action_source)
        if (($serverEvent['action_source'] === 'website') && !empty($event['event_source_url'])) {
            $serverEvent['event_source_url'] = $event['event_source_url'];
        } elseif ($serverEvent['action_source'] === 'website') {
            // Auto-detect from request if available
            $serverEvent['event_source_url'] = $event['page_url'] ?? $event['url'] ?? request()?->header('Referer') ?? '';
        }

        // Opt-out flag (for measurement-only, no optimization)
        if (!empty($event['opt_out'])) {
            $serverEvent['opt_out'] = true;
        }

        // ── User Data (Advanced Matching) ──────────────────────
        $serverEvent['user_data'] = $this->buildUserData($event);

        // ── Custom Data ────────────────────────────────────────
        if (!empty($event['custom_data'])) {
            $serverEvent['custom_data'] = $this->buildCustomData($event['custom_data']);
        }

        // ── Data Processing Options (CCPA / LDU Compliance) ───
        if (!empty($options['data_processing_options'])) {
            $serverEvent['data_processing_options'] = $options['data_processing_options'];
            $serverEvent['data_processing_options_country'] = $options['data_processing_options_country'] ?? 0;
            $serverEvent['data_processing_options_state'] = $options['data_processing_options_state'] ?? 0;
        }

        return $serverEvent;
    }

    /**
     * Build user_data with proper hashing.
     * Hashes PII fields (em, ph, fn, ln, etc.) with SHA-256.
     * Passes through non-PII fields (client_ip_address, fbc, fbp, etc.) without hashing.
     */
    private function buildUserData(array $event): array
    {
        $rawUserData = $event['user_data'] ?? [];
        $userData = [];

        // Map common field names to Meta's short codes
        $fieldAliases = [
            'email'            => 'em',
            'phone'            => 'ph',
            'first_name'       => 'fn',
            'last_name'        => 'ln',
            'gender'           => 'ge',
            'date_of_birth'    => 'db',
            'city'             => 'ct',
            'state'            => 'st',
            'zip'              => 'zp',
            'zip_code'         => 'zp',
            'postal_code'      => 'zp',
            'ip'               => 'client_ip_address',
            'ip_address'       => 'client_ip_address',
            'source_ip'        => 'client_ip_address',
            'user_agent'       => 'client_user_agent',
            'click_id'         => 'fbc',
            'browser_id'       => 'fbp',
        ];

        // Normalize field names
        foreach ($rawUserData as $key => $value) {
            $normalizedKey = $fieldAliases[$key] ?? $key;
            $userData[$normalizedKey] = $value;
        }

        // Auto-inject IP and User-Agent from request if not provided
        if (empty($userData['client_ip_address'])) {
            $userData['client_ip_address'] = request()?->ip() ?? '';
        }
        if (empty($userData['client_user_agent'])) {
            $userData['client_user_agent'] = request()?->userAgent() ?? '';
        }

        // Auto-detect fbc from _fbc cookie or fbclid query param
        if (empty($userData['fbc'])) {
            $fbc = request()?->cookie('_fbc');
            if (!$fbc) {
                $fbclid = $event['fbclid'] ?? request()?->get('fbclid');
                if ($fbclid) {
                    // Format: fb.{subdomain_index}.{creation_time}.{fbclid}
                    $fbc = "fb.1." . time() . ".{$fbclid}";
                }
            }
            if ($fbc) {
                $userData['fbc'] = $fbc;
            }
        }

        // Auto-detect fbp from _fbp cookie
        if (empty($userData['fbp'])) {
            $fbp = request()?->cookie('_fbp');
            if ($fbp) {
                $userData['fbp'] = $fbp;
            }
        }

        // Apply SHA-256 hashing to PII fields
        foreach (self::HASH_FIELDS as $field) {
            if (isset($userData[$field]) && !$this->isAlreadyHashed($userData[$field])) {
                $userData[$field] = $this->hashValue($userData[$field], $field);
            }
        }

        return $userData;
    }

    /**
     * Build custom_data with Meta-standard fields.
     */
    private function buildCustomData(array $customData): array
    {
        $result = [];

        // Standard custom_data fields
        $standardFields = [
            'value', 'currency', 'content_name', 'content_category',
            'content_ids', 'content_type', 'contents', 'num_items',
            'predicted_ltv', 'search_string', 'status', 'delivery_category',
            'order_id',
        ];

        foreach ($standardFields as $field) {
            if (isset($customData[$field])) {
                $result[$field] = $customData[$field];
            }
        }

        // Pass through any additional custom fields
        foreach ($customData as $key => $value) {
            if (!isset($result[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Hash a value using SHA-256 with proper normalization.
     */
    private function hashValue(string $value, string $field): string
    {
        // Normalize based on field type
        $value = trim($value);

        switch ($field) {
            case 'em':
                $value = strtolower($value);
                break;
            case 'ph':
                // Remove non-numeric except leading +, then strip +
                $value = preg_replace('/[^\d]/', '', $value);
                break;
            case 'fn':
            case 'ln':
            case 'ct':
            case 'st':
            case 'country':
                $value = strtolower($value);
                break;
            case 'zp':
                // US: only first 5 digits
                if (strlen($value) > 5 && preg_match('/^\d{5}/', $value)) {
                    $value = substr($value, 0, 5);
                }
                break;
            case 'ge':
                $value = strtolower(substr($value, 0, 1)); // 'm' or 'f'
                break;
            case 'db':
                // Normalize to YYYYMMDD
                $value = preg_replace('/[^0-9]/', '', $value);
                break;
        }

        return hash('sha256', $value);
    }

    /**
     * Check if a value appears to already be SHA-256 hashed.
     */
    private function isAlreadyHashed(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $value);
    }

    /**
     * Normalize event name — map common variations to Meta standards.
     */
    private function normalizeEventName(string $eventName): string
    {
        $map = [
            'page_view'       => 'PageView',
            'pageview'        => 'PageView',
            'purchase'        => 'Purchase',
            'add_to_cart'     => 'AddToCart',
            'view_content'    => 'ViewContent',
            'begin_checkout'  => 'InitiateCheckout',
            'initiate_checkout' => 'InitiateCheckout',
            'sign_up'         => 'CompleteRegistration',
            'register'        => 'CompleteRegistration',
            'search'          => 'Search',
            'add_payment_info' => 'AddPaymentInfo',
            'add_to_wishlist' => 'AddToWishlist',
            'contact'         => 'Contact',
            'lead'            => 'Lead',
            'subscribe'       => 'Subscribe',
            'start_trial'     => 'StartTrial',
            'schedule'        => 'Schedule',
        ];

        return $map[strtolower($eventName)] ?? $eventName;
    }

    /**
     * Validate action_source value.
     */
    private function validateActionSource(string $source): string
    {
        return in_array($source, self::ACTION_SOURCES) ? $source : 'website';
    }

    /**
     * Calculate Event Match Quality (EMQ) Score.
     * Estimates how well Meta can match this event to a user.
     *
     * Score: 0-10 (Meta standard)
     *   10 = Excellent (em + ph + fbc + fbp + ip + ua)
     *   7-9 = Good
     *   4-6 = Fair
     *   1-3 = Poor
     */
    public function calculateEMQ(array $userData): array
    {
        $score = 0;
        $matchedFields = [];
        $missingFields = [];

        $weights = [
            'em'                 => 3.0,  // Email is strongest signal
            'ph'                 => 2.0,
            'fbc'                => 1.5,  // Click ID
            'fbp'                => 1.0,  // Browser ID
            'client_ip_address'  => 0.5,
            'client_user_agent'  => 0.5,
            'external_id'       => 1.0,
            'fn'                => 0.3,
            'ln'                => 0.3,
            'ct'                => 0.2,
            'st'                => 0.2,
            'zp'                => 0.2,
            'country'           => 0.2,
            'ge'                => 0.1,
            'db'                => 0.1,
        ];

        foreach ($weights as $field => $weight) {
            if (!empty($userData[$field])) {
                $score += $weight;
                $matchedFields[] = $field;
            } else {
                $missingFields[] = $field;
            }
        }

        // Cap at 10
        $normalizedScore = min(10, round($score));

        $quality = match (true) {
            $normalizedScore >= 8 => 'excellent',
            $normalizedScore >= 6 => 'good',
            $normalizedScore >= 4 => 'fair',
            default               => 'poor',
        };

        return [
            'score'          => $normalizedScore,
            'quality'        => $quality,
            'matched_fields' => $matchedFields,
            'missing_fields' => array_slice($missingFields, 0, 5), // Top 5 missing
            'recommendation' => $this->getEMQRecommendation($normalizedScore, $missingFields),
        ];
    }

    /**
     * Get actionable recommendation based on EMQ score.
     */
    private function getEMQRecommendation(int $score, array $missing): string
    {
        if ($score >= 8) {
            return 'Excellent match quality. Your events will be well-matched.';
        }

        $tips = [];
        if (in_array('em', $missing)) $tips[] = 'Add hashed email for strongest matching';
        if (in_array('ph', $missing)) $tips[] = 'Add hashed phone number';
        if (in_array('fbc', $missing)) $tips[] = 'Ensure _fbc cookie is passed (click ID tracking)';
        if (in_array('fbp', $missing)) $tips[] = 'Ensure _fbp cookie is passed (browser ID)';

        return implode('. ', array_slice($tips, 0, 3)) . '.';
    }
}
