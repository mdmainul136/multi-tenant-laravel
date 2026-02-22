<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlockedSourceService
{
    /**
     * Check if a URL belongs to a blocked marketplace or domain.
     */
    public function isBlocked(string $url): bool
    {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) return false;

        $domain = strtolower($domain);
        
        // Remove 'www.' prefix for consistency
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return DB::table('ior_blocked_sources')
            ->where('domain', $domain)
            ->exists();
    }

    /**
     * Block a new domain.
     */
    public function blockDomain(string $domain, ?string $reason = null): void
    {
        $domain = strtolower($domain);
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        DB::table('ior_blocked_sources')->updateOrInsert(
            ['domain' => $domain],
            ['reason' => $reason, 'updated_at' => now(), 'created_at' => now()]
        );
        
        Log::warning("[IOR Hardening] Blocked domain: {$domain}");
    }

    /**
     * Unblock a domain.
     */
    public function unblockDomain(string $domain): void
    {
        DB::table('ior_blocked_sources')->where('domain', $domain)->delete();
        Log::info("[IOR Hardening] Unblocked domain: {$domain}");
    }

    /**
     * Check if the scraper is globally enabled or for a specific marketplace.
     */
    public static function isScraperEnabled(?string $marketplace = null): bool
    {
        $settings = \DB::table('ior_scraper_settings')->where('is_active', true)->first();
        if (!$settings) return true;

        if (!$settings->is_active) return false;

        if ($marketplace) {
            $allowed = json_decode($settings->allowed_marketplaces ?? '[]', true);
            if (!empty($allowed) && !in_array($marketplace, $allowed)) {
                return false;
            }
        }

        return true;
    }
}
