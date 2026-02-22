<?php

namespace App\Modules\CrossBorderIOR\Services;

use App\Models\LandlordIorProxy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProxyRegistryService
{
    /**
     * Get a healthy proxy, rotation-aware.
     */
    public function getProxy(?string $country = null, ?string $sessionId = null): ?LandlordIorProxy
    {
        $query = LandlordIorProxy::where('is_active', true)
            ->where('score', '>', 50) // Only usable proxies
            ->orderBy('last_used_at', 'asc');

        if ($country) {
            $query->where('country_code', strtoupper($country));
        }

        $proxy = $query->first();

        if ($proxy) {
            $proxy->update(['last_used_at' => now()]);
        }

        return $proxy;
    }

    /**
     * Generate a session-based proxy string for providers like Oxylabs/BrightData.
     * Format: username-session-id:password@host:port
     */
    public function getRotatedProxyString(LandlordIorProxy $proxy, ?string $sessionId = null): string
    {
        $id = $sessionId ?: Str::random(8);
        $user = $proxy->username;
        
        // Handle provider-specific rotation flags
        if (str_contains(strtolower($proxy->provider), 'oxylabs')) {
            $user = "{$proxy->username}-session-{$id}";
        } elseif (str_contains(strtolower($proxy->provider), 'brightdata')) {
            $user = "{$proxy->username}-session-{$id}";
        }

        return "http://{$user}:{$proxy->password}@{$proxy->host}:{$proxy->port}";
    }

    /**
     * Report proxy failure and adjust score.
     */
    public function reportFailure(int $proxyId, string $error): void
    {
        $proxy = LandlordIorProxy::find($proxyId);
        if ($proxy) {
            $newScore = max(0, $proxy->score - 20);
            $proxy->update([
                'fail_count'     => $proxy->fail_count + 1,
                'score'          => $newScore,
                'last_failed_at' => now(),
                'is_active'      => $newScore > 0
            ]);
            
            Log::warning("[ProxyRegistry] Negative score update for ID {$proxyId}: {$error}. New Score: {$newScore}");
        }
    }

    /**
     * Report proxy success and boost score.
     */
    public function reportSuccess(int $proxyId): void
    {
        $proxy = LandlordIorProxy::find($proxyId);
        if ($proxy) {
            $proxy->update([
                'success_count' => $proxy->success_count + 1,
                'score'         => min(100, $proxy->score + 5)
            ]);
        }
    }

    /**
     * Get a random mobile User-Agent for anti-fingerprinting.
     */
    public function getRandomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        ];

        return $agents[array_rand($agents)];
    }
}
