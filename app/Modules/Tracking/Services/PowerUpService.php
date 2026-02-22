<?php

namespace App\Modules\Tracking\Services;

use App\Models\Tracking\TrackingContainer;

/**
 * Central Power-Ups service.
 * Checks which power-ups are enabled for a container and applies them.
 *
 * Power-Up Registry:
 *   dedupe        - Event deduplication (Free)
 *   pii_hash      - Automatic PII hashing (Free)
 *   consent_mode  - Consent-based filtering (Free)
 *   cookie_extend - First-party cookie extension (Pro)
 *   geo_enrich    - Geo-IP enrichment (Pro)
 *   bot_filter    - Advanced bot filtering (Pro)
 */
class PowerUpService
{
    /**
     * Default free power-ups enabled for every container.
     */
    private const FREE_POWERUPS = ['dedupe', 'pii_hash', 'consent_mode'];

    /**
     * Check if a specific power-up is enabled for a container.
     */
    public function isEnabled(TrackingContainer $container, string $powerUp): bool
    {
        $enabled = $container->power_ups ?? self::FREE_POWERUPS;
        return in_array($powerUp, $enabled, true);
    }

    /**
     * Get all enabled power-ups for a container.
     */
    public function getEnabled(TrackingContainer $container): array
    {
        return $container->power_ups ?? self::FREE_POWERUPS;
    }

    /**
     * Get the full registry of available power-ups.
     */
    public static function registry(): array
    {
        return [
            // ── Free Tier ──
            'dedupe'           => ['name' => 'Event Deduplication',    'tier' => 'free',  'description' => 'Prevents duplicate events from being forwarded'],
            'pii_hash'         => ['name' => 'PII Hashing',           'tier' => 'free',  'description' => 'SHA-256 hashes email, phone, external_id'],
            'consent_mode'     => ['name' => 'Consent Mode',          'tier' => 'free',  'description' => 'Drops events without user consent'],
            // ── Pro Tier ──
            'cookie_extend'    => ['name' => 'Cookie Keeper',         'tier' => 'pro',   'description' => 'Sets server-side first-party cookies (1yr)'],
            'geo_enrich'       => ['name' => 'Geo-IP Enrichment',     'tier' => 'pro',   'description' => 'Adds country/city/region from IP headers'],
            'bot_filter'       => ['name' => 'Advanced Bot Filter',   'tier' => 'pro',   'description' => 'Blocks known bot patterns & IPs'],
            'custom_loader'    => ['name' => 'Custom GTM Loader',     'tier' => 'pro',   'description' => 'Obfuscated script paths to bypass ad blockers (+40% data)'],
            'click_id_restore' => ['name' => 'Click ID Restorer',     'tier' => 'pro',   'description' => 'Recovers stripped gclid/fbclid/msclkid from Safari/Brave'],
            'phone_formatter'  => ['name' => 'Phone E.164 Formatter', 'tier' => 'pro',   'description' => 'Auto-formats phone numbers to international E.164 standard'],
            'request_delay'    => ['name' => 'Request Delay',         'tier' => 'pro',   'description' => 'Delay event forwarding for attribution window optimization'],
            'poas'             => ['name' => 'POAS Calculator',       'tier' => 'pro',   'description' => 'Calculates Profit on Ad Spend and adjusts conversion values'],
        ];
    }
}
