<?php

namespace App\Modules\Tracking\Services;

/**
 * Direct Integration Guide Generator.
 *
 * Generates ready-to-use code snippets for tenants to integrate CAPI & Pixel
 * on their websites. Covers both browser-side (Pixel) and server-side (CAPI)
 * with proper deduplication via shared event_id.
 *
 * Equivalent to Meta's "Direct Integration" setup flow but customized
 * for our proxy infrastructure.
 */
class DirectIntegrationService
{
    /**
     * Generate full integration kit for a container.
     */
    public function generateIntegrationKit(array $containerConfig): array
    {
        $pixelId  = $containerConfig['pixel_id'] ?? '';
        $domain   = $containerConfig['domain'] ?? '';
        $loaderPath = $containerConfig['loader_path'] ?? '/gtm.js';
        $collectPath = $containerConfig['collect_path'] ?? '/collect';

        return [
            'browser_snippet' => $this->generatePixelSnippet($pixelId, $domain, $loaderPath),
            'server_snippet'  => $this->generateServerSnippet($pixelId, $domain, $collectPath),
            'dedup_snippet'   => $this->generateDedupSnippet(),
            'enhanced_matching' => $this->generateEnhancedMatchingSnippet($pixelId),
            'instructions'    => $this->getSetupInstructions($domain, $loaderPath),
        ];
    }

    /**
     * Generate browser-side Pixel snippet with our custom loader for ad-blocker bypass.
     */
    private function generatePixelSnippet(string $pixelId, string $domain, string $loaderPath): string
    {
        return <<<HTML
<!-- Meta Pixel (via Server-Side Proxy) -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src='https://{$domain}{$loaderPath}?id={$pixelId}';
s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script');

fbq('init', '{$pixelId}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://{$domain}/tr?id={$pixelId}&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel -->
HTML;
    }

    /**
     * Generate server-side CAPI snippet (PHP/cURL example).
     */
    private function generateServerSnippet(string $pixelId, string $domain, string $collectPath): string
    {
        return <<<'PHP'
<?php
/**
 * Server-Side Event Sending via Conversions API Proxy.
 *
 * Send this from your backend after key conversions (Purchase, Lead, etc.)
 * The event_id MUST match the browser Pixel event_id for deduplication.
 */
function sendServerEvent($eventName, $eventData, $userData) {
    $payload = [
        'events' => [[
            'event_name'      => $eventName,
            'event_time'      => time(),
            'event_id'        => $eventData['event_id'] ?? uniqid('evt_'),
            'action_source'   => 'website',
            'event_source_url' => $eventData['url'] ?? $_SERVER['HTTP_REFERER'] ?? '',
            'user_data' => [
                'em'                 => $userData['email'] ?? '',   // Will be auto-hashed
                'ph'                 => $userData['phone'] ?? '',   // Will be auto-hashed
                'fn'                 => $userData['first_name'] ?? '',
                'ln'                 => $userData['last_name'] ?? '',
                'client_ip_address'  => $_SERVER['REMOTE_ADDR'],
                'client_user_agent'  => $_SERVER['HTTP_USER_AGENT'],
                'fbc'                => $_COOKIE['_fbc'] ?? '',
                'fbp'                => $_COOKIE['_fbp'] ?? '',
                'external_id'        => $userData['user_id'] ?? '',
            ],
            'custom_data' => $eventData['custom_data'] ?? [],
        ]],
        'container_id' => YOUR_CONTAINER_ID,
    ];

    $ch = curl_init('https://YOUR_DOMAIN/api/tracking/signals/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer YOUR_API_TOKEN',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Usage Example — Purchase Event:
sendServerEvent('Purchase', [
    'event_id'    => $_POST['event_id'],  // Same as browser Pixel
    'url'         => 'https://yoursite.com/thank-you',
    'custom_data' => [
        'value'    => 99.99,
        'currency' => 'USD',
        'content_ids' => ['SKU-123'],
        'content_type' => 'product',
    ],
], [
    'email'      => $customer->email,
    'phone'      => $customer->phone,
    'first_name' => $customer->first_name,
    'last_name'  => $customer->last_name,
    'user_id'    => $customer->id,
]);
PHP;
    }

    /**
     * Generate deduplication snippet (shared event_id between Pixel and CAPI).
     */
    private function generateDedupSnippet(): string
    {
        return <<<'JS'
/**
 * Event Deduplication
 *
 * Generate a unique event_id on the browser and pass it to both:
 * 1. fbq('track', ...) — Browser Pixel
 * 2. Your server-side CAPI call
 *
 * Meta will automatically deduplicate events with matching event_name + event_id.
 */

function generateEventId() {
  return 'evt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Browser-side: send with Pixel
const eventId = generateEventId();
fbq('track', 'Purchase', {
  value: 99.99,
  currency: 'USD',
  content_ids: ['SKU-123'],
  content_type: 'product',
}, { eventID: eventId });

// Pass eventId to your server (via form, AJAX, or data attribute)
document.getElementById('checkout-form').dataset.eventId = eventId;
// Then include it in your server-side CAPI call
JS;
    }

    /**
     * Generate Enhanced Matching snippet for better EMQ.
     */
    private function generateEnhancedMatchingSnippet(string $pixelId): string
    {
        return <<<JS
/**
 * Enhanced Matching — Boost your EMQ score.
 *
 * Pass user data during fbq('init') for automatic hashing and matching.
 * This data is hashed locally before being sent to Meta.
 */
fbq('init', '{$pixelId}', {
  em: 'user@example.com',           // Email
  ph: '1234567890',                 // Phone (digits only)
  fn: 'john',                       // First name (lowercase)
  ln: 'doe',                        // Last name (lowercase)
  ge: 'm',                          // Gender: 'm' or 'f'
  db: '19900115',                   // Date of birth: YYYYMMDD
  ct: 'new york',                   // City (lowercase)
  st: 'ny',                         // State (2-letter code)
  zp: '10001',                      // Zip code
  country: 'us',                    // Country (2-letter ISO)
  external_id: 'USER_123',          // Your internal user ID
});
JS;
    }

    /**
     * Generate step-by-step setup instructions.
     */
    private function getSetupInstructions(string $domain, string $loaderPath): array
    {
        return [
            [
                'step'  => 1,
                'title' => 'Add the Browser Pixel Snippet',
                'desc'  => "Paste the browser snippet in the <head> of every page. It loads the Pixel via your custom proxy at {$domain}{$loaderPath} to bypass ad blockers.",
            ],
            [
                'step'  => 2,
                'title' => 'Set Up Server-Side Events',
                'desc'  => 'Add the server snippet to your backend. Call it after key conversion events (Purchase, Lead, AddToCart, etc.).',
            ],
            [
                'step'  => 3,
                'title' => 'Enable Deduplication',
                'desc'  => 'Generate a shared event_id on the browser and pass it to both the Pixel and your CAPI call. This prevents duplicate counting.',
            ],
            [
                'step'  => 4,
                'title' => 'Enable Enhanced Matching',
                'desc'  => 'Pass user data (email, phone, name) during fbq(\'init\') for higher Event Match Quality scores.',
            ],
            [
                'step'  => 5,
                'title' => 'Verify in Events Manager',
                'desc'  => 'Use Meta Events Manager → Test Events to verify both browser and server events are arriving. Check the Dataset Quality tab for your EMQ score.',
            ],
        ];
    }
}
