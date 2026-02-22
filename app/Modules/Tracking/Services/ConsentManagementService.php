<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Consent Management Service.
 *
 * GDPR / CCPA / ePrivacy compliant consent handling:
 *   - Store and retrieve per-visitor consent preferences
 *   - Consent categories: analytics, marketing, functional, personalization
 *   - Consent expiration (configurable, default 13 months)
 *   - Consent-based destination routing decisions
 *   - Audit trail for consent changes
 *   - Consent banner configuration for tenants
 */
class ConsentManagementService
{
    private const TABLE = 'ec_tracking_consent';
    private const DEFAULT_EXPIRY_MONTHS = 13; // GDPR recommendation

    /**
     * Destination → required consent category mapping.
     */
    private const CHANNEL_CONSENT_MAP = [
        'facebook_capi' => 'marketing',
        'google_ads'    => 'marketing',
        'tiktok'        => 'marketing',
        'snapchat'      => 'marketing',
        'pinterest'     => 'marketing',
        'linkedin'      => 'marketing',
        'twitter'       => 'marketing',
        'ga4'           => 'analytics',
        'webhook'       => 'functional',
    ];

    /**
     * Record or update consent for a visitor.
     */
    public function recordConsent(int $containerId, string $visitorId, array $consent, ?array $meta = []): array
    {
        $data = [
            'container_id'    => $containerId,
            'visitor_id'      => $visitorId,
            'analytics'       => $consent['analytics'] ?? false,
            'marketing'       => $consent['marketing'] ?? false,
            'functional'      => $consent['functional'] ?? true,
            'personalization' => $consent['personalization'] ?? false,
            'consent_source'  => $meta['source'] ?? 'banner',
            'ip_address'      => $meta['ip'] ?? request()?->ip(),
            'user_agent'      => substr($meta['user_agent'] ?? request()?->userAgent() ?? '', 0, 500),
            'raw_consent'     => json_encode($consent),
            'consented_at'    => now(),
            'expires_at'      => Carbon::now()->addMonths($meta['expiry_months'] ?? self::DEFAULT_EXPIRY_MONTHS),
            'updated_at'      => now(),
        ];

        DB::table(self::TABLE)->updateOrInsert(
            ['container_id' => $containerId, 'visitor_id' => $visitorId],
            array_merge($data, ['created_at' => now()])
        );

        return [
            'visitor_id' => $visitorId,
            'consent'    => [
                'analytics'       => $data['analytics'],
                'marketing'       => $data['marketing'],
                'functional'      => $data['functional'],
                'personalization' => $data['personalization'],
            ],
            'expires_at' => $data['expires_at']->toIso8601String(),
        ];
    }

    /**
     * Get current consent for a visitor.
     */
    public function getConsent(int $containerId, string $visitorId): ?array
    {
        $record = DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('visitor_id', $visitorId)
            ->first();

        if (!$record) return null;

        // Check expiration
        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            return [
                'expired'  => true,
                'consent'  => $this->defaultConsent(),
                'message'  => 'Consent has expired. Please re-consent.',
            ];
        }

        return [
            'expired' => false,
            'consent' => [
                'analytics'       => (bool) $record->analytics,
                'marketing'       => (bool) $record->marketing,
                'functional'      => (bool) $record->functional,
                'personalization' => (bool) $record->personalization,
            ],
            'consented_at' => $record->consented_at,
            'expires_at'   => $record->expires_at,
        ];
    }

    /**
     * Check if a specific destination is allowed based on visitor consent.
     */
    public function isDestinationAllowed(int $containerId, string $visitorId, string $destinationType): bool
    {
        $consent = $this->getConsent($containerId, $visitorId);

        // No consent recorded → deny marketing, allow functional
        if (!$consent || ($consent['expired'] ?? false)) {
            $requiredCategory = self::CHANNEL_CONSENT_MAP[$destinationType] ?? 'marketing';
            return $requiredCategory === 'functional';
        }

        $requiredCategory = self::CHANNEL_CONSENT_MAP[$destinationType] ?? 'marketing';
        return (bool) ($consent['consent'][$requiredCategory] ?? false);
    }

    /**
     * Filter destinations based on visitor consent.
     * Returns only the destination types the visitor has consented to.
     */
    public function filterAllowedDestinations(int $containerId, string $visitorId, array $destinationTypes): array
    {
        return array_values(array_filter(
            $destinationTypes,
            fn ($type) => $this->isDestinationAllowed($containerId, $visitorId, $type)
        ));
    }

    /**
     * Revoke all consent for a visitor (GDPR right to withdraw).
     */
    public function revokeConsent(int $containerId, string $visitorId): bool
    {
        return DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('visitor_id', $visitorId)
            ->update([
                'analytics'       => false,
                'marketing'       => false,
                'personalization' => false,
                'consent_source'  => 'revoked',
                'updated_at'      => now(),
            ]) > 0;
    }

    /**
     * Get consent statistics for a container.
     */
    public function getConsentStats(int $containerId): array
    {
        $total = DB::table(self::TABLE)->where('container_id', $containerId)->count();

        if ($total === 0) {
            return ['total' => 0, 'analytics' => 0, 'marketing' => 0, 'functional' => 0, 'personalization' => 0];
        }

        return [
            'total'           => $total,
            'analytics'       => DB::table(self::TABLE)->where('container_id', $containerId)->where('analytics', true)->count(),
            'marketing'       => DB::table(self::TABLE)->where('container_id', $containerId)->where('marketing', true)->count(),
            'functional'      => DB::table(self::TABLE)->where('container_id', $containerId)->where('functional', true)->count(),
            'personalization' => DB::table(self::TABLE)->where('container_id', $containerId)->where('personalization', true)->count(),
            'rates' => [
                'analytics'       => round(DB::table(self::TABLE)->where('container_id', $containerId)->where('analytics', true)->count() / $total * 100, 1),
                'marketing'       => round(DB::table(self::TABLE)->where('container_id', $containerId)->where('marketing', true)->count() / $total * 100, 1),
            ],
        ];
    }

    /**
     * Generate consent banner configuration for a container.
     */
    public function getBannerConfig(int $containerId, ?array $overrides = []): array
    {
        return array_merge([
            'container_id' => $containerId,
            'categories' => [
                [
                    'id'          => 'functional',
                    'name'        => 'Essential',
                    'description' => 'Required for the website to function properly.',
                    'required'    => true,
                    'default'     => true,
                ],
                [
                    'id'          => 'analytics',
                    'name'        => 'Analytics',
                    'description' => 'Help us understand how visitors interact with our website.',
                    'required'    => false,
                    'default'     => false,
                ],
                [
                    'id'          => 'marketing',
                    'name'        => 'Marketing',
                    'description' => 'Used to deliver personalized advertisements.',
                    'required'    => false,
                    'default'     => false,
                ],
                [
                    'id'          => 'personalization',
                    'name'        => 'Personalization',
                    'description' => 'Allow us to remember your preferences and customize your experience.',
                    'required'    => false,
                    'default'     => false,
                ],
            ],
            'consent_endpoint' => "/api/tracking/consent/{$containerId}",
            'expiry_months'    => self::DEFAULT_EXPIRY_MONTHS,
        ], $overrides);
    }

    private function defaultConsent(): array
    {
        return [
            'analytics'       => false,
            'marketing'       => false,
            'functional'      => true,
            'personalization' => false,
        ];
    }
}
