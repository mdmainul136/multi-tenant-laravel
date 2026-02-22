<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Str;

/**
 * Event Enrichment Pipeline.
 *
 * Auto-enriches tracking events with:
 *   - Geo-IP data (country, city, region from IP)
 *   - Device detection (mobile/desktop/tablet, OS, browser)
 *   - Session stitching (assign/continue session based on visitor)
 *   - UTM parameter parsing from URL
 *   - Click ID extraction (gclid, fbclid, ttclid, etc.)
 *   - Referrer classification (organic, paid, social, direct, email)
 *   - Timestamp normalization
 */
class EventEnrichmentService
{
    /**
     * Run full enrichment pipeline on an event.
     */
    public function enrich(array $event): array
    {
        $event = $this->enrichDeviceInfo($event);
        $event = $this->enrichGeoIP($event);
        $event = $this->enrichUTMParams($event);
        $event = $this->enrichClickIds($event);
        $event = $this->enrichReferrer($event);
        $event = $this->enrichSession($event);
        $event = $this->normalizeTimestamp($event);

        $event['_enriched'] = true;
        $event['_enriched_at'] = now()->toIso8601String();

        return $event;
    }

    /**
     * Device detection from User-Agent.
     */
    private function enrichDeviceInfo(array $event): array
    {
        $ua = $event['user_data']['client_user_agent']
            ?? $event['user_agent']
            ?? request()?->userAgent()
            ?? '';

        if (empty($ua)) return $event;

        $device = [
            'type'    => $this->detectDeviceType($ua),
            'os'      => $this->detectOS($ua),
            'browser' => $this->detectBrowser($ua),
            'is_bot'  => $this->detectBot($ua),
        ];

        $event['device'] = $device;
        return $event;
    }

    /**
     * Geo-IP enrichment from client IP.
     */
    private function enrichGeoIP(array $event): array
    {
        $ip = $event['user_data']['client_ip_address']
            ?? $event['source_ip']
            ?? request()?->ip()
            ?? '';

        if (empty($ip) || $ip === '127.0.0.1') return $event;

        // Lightweight geo from IP — uses MaxMind GeoLite2 or ip-api.com fallback
        $geo = $this->lookupGeo($ip);
        if ($geo) {
            $event['geo'] = $geo;
        }

        return $event;
    }

    /**
     * Parse UTM parameters from event_source_url.
     */
    private function enrichUTMParams(array $event): array
    {
        $url = $event['event_source_url'] ?? '';
        if (empty($url)) return $event;

        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) return $event;

        parse_str($query, $params);

        $utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $utmData = [];

        foreach ($utmFields as $field) {
            if (!empty($params[$field])) {
                $utmData[$field] = $params[$field];
            }
        }

        if (!empty($utmData)) {
            $event['utm'] = $utmData;
        }

        return $event;
    }

    /**
     * Extract and classify click IDs from URL and cookies.
     */
    private function enrichClickIds(array $event): array
    {
        $url = $event['event_source_url'] ?? '';
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($query, $params);

        $clickIdMap = [
            'gclid'   => 'google_ads',
            'gbraid'  => 'google_ads',
            'wbraid'  => 'google_ads',
            'fbclid'  => 'facebook',
            'ttclid'  => 'tiktok',
            'ScCid'   => 'snapchat',
            'epik'    => 'pinterest',
            'li_fat_id' => 'linkedin',
            'twclid'  => 'twitter',
            'msclkid' => 'microsoft_ads',
        ];

        $clickIds = [];
        foreach ($clickIdMap as $param => $platform) {
            $value = $params[$param] ?? request()?->cookie("_{$param}") ?? null;
            if ($value) {
                $clickIds[$param] = [
                    'value'    => $value,
                    'platform' => $platform,
                ];
            }
        }

        if (!empty($clickIds)) {
            $event['click_ids'] = $clickIds;
        }

        return $event;
    }

    /**
     * Classify referrer source.
     */
    private function enrichReferrer(array $event): array
    {
        $referrer = $event['referrer'] ?? request()?->header('Referer') ?? '';
        if (empty($referrer)) {
            $event['traffic_source'] = 'direct';
            return $event;
        }

        $event['referrer_url'] = $referrer;
        $host = strtolower(parse_url($referrer, PHP_URL_HOST) ?? '');

        // Classify
        $searchEngines = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.'];
        $socials = ['facebook.', 'instagram.', 'twitter.', 't.co', 'linkedin.', 'tiktok.', 'pinterest.', 'reddit.', 'youtube.'];

        $event['traffic_source'] = 'referral';

        foreach ($searchEngines as $engine) {
            if (str_contains($host, $engine)) {
                $event['traffic_source'] = !empty($event['click_ids']['gclid'] ?? $event['click_ids']['msclkid'] ?? null)
                    ? 'paid_search'
                    : 'organic_search';
                break;
            }
        }

        foreach ($socials as $social) {
            if (str_contains($host, $social)) {
                $hasPaidClickId = !empty($event['click_ids']['fbclid'] ?? $event['click_ids']['ttclid'] ?? null);
                $event['traffic_source'] = $hasPaidClickId ? 'paid_social' : 'organic_social';
                break;
            }
        }

        if (str_contains($referrer, 'email') || str_contains($event['utm']['utm_medium'] ?? '', 'email')) {
            $event['traffic_source'] = 'email';
        }

        return $event;
    }

    /**
     * Session stitching.
     */
    private function enrichSession(array $event): array
    {
        if (empty($event['session_id'])) {
            // Use fbp or generate one
            $event['session_id'] = $event['user_data']['fbp']
                ?? request()?->cookie('_session_id')
                ?? 'sess_' . Str::random(16);
        }

        if (empty($event['visitor_id'])) {
            $event['visitor_id'] = $event['user_data']['external_id']
                ?? $event['user_data']['fbp']
                ?? request()?->cookie('_visitor_id')
                ?? 'vis_' . Str::random(16);
        }

        return $event;
    }

    /**
     * Normalize event timestamp.
     */
    private function normalizeTimestamp(array $event): array
    {
        if (!isset($event['event_time'])) {
            $event['event_time'] = time();
        }

        // Convert milliseconds to seconds if needed
        if ($event['event_time'] > 9999999999) {
            $event['event_time'] = (int) ($event['event_time'] / 1000);
        }

        return $event;
    }

    // ── Detection Helpers ──────────────────────────────

    private function detectDeviceType(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/(tablet|ipad|playbook|silk)/', $ua)) return 'tablet';
        if (preg_match('/(mobile|android|iphone|ipod|phone|blackberry|opera mini|iemobile)/', $ua)) return 'mobile';
        return 'desktop';
    }

    private function detectOS(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows')    => 'Windows',
            str_contains($ua, 'Mac OS')     => 'macOS',
            str_contains($ua, 'iPhone')     || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android')    => 'Android',
            str_contains($ua, 'Linux')      => 'Linux',
            str_contains($ua, 'CrOS')       => 'Chrome OS',
            default                         => 'Unknown',
        };
    }

    private function detectBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/')       => 'Edge',
            str_contains($ua, 'OPR/')       || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/')    && !str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Safari/')    && !str_contains($ua, 'Chrome') => 'Safari',
            str_contains($ua, 'Firefox/')   => 'Firefox',
            str_contains($ua, 'MSIE')       || str_contains($ua, 'Trident') => 'IE',
            default                         => 'Unknown',
        };
    }

    private function detectBot(string $ua): bool
    {
        $bots = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'lighthouse', 'pagespeed', 'headless'];
        $ua = strtolower($ua);
        foreach ($bots as $bot) {
            if (str_contains($ua, $bot)) return true;
        }
        return false;
    }

    /**
     * Geo-IP lookup (lightweight — uses ip-api.com free tier as fallback).
     */
    private function lookupGeo(string $ip): ?array
    {
        // Try cached value first
        $cacheKey = "geo_ip_{$ip}";
        $cached = cache($cacheKey);
        if ($cached) return $cached;

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp");

            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json();
                $geo = [
                    'country'      => $data['country'] ?? '',
                    'country_code' => $data['countryCode'] ?? '',
                    'region'       => $data['regionName'] ?? '',
                    'region_code'  => $data['region'] ?? '',
                    'city'         => $data['city'] ?? '',
                    'zip'          => $data['zip'] ?? '',
                    'latitude'     => $data['lat'] ?? 0,
                    'longitude'    => $data['lon'] ?? 0,
                    'timezone'     => $data['timezone'] ?? '',
                    'isp'          => $data['isp'] ?? '',
                ];

                cache([$cacheKey => $geo], 86400); // Cache 24h
                return $geo;
            }
        } catch (\Exception $e) {
            // Silent fail — geo is optional enrichment
        }

        return null;
    }
}
