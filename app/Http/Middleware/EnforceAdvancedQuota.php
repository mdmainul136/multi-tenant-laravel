<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdvancedQuota
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->attributes->get('tenant_id') ?: $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return $next($request);
        }

        // 1. Identify Tenant Tier
        $tenant = Tenant::where('tenant_id', $tenantId)->first();
        if (!$tenant) return $next($request);

        $tier = $tenant->subscription_tier ?: 'free';
        
        // Load tier quotas from config
        $quotas = config("tenant_quotas.tiers.{$tier}") ?: config("tenant_quotas.tiers.free");

        // 2. Global API Rate Limiting (RPM)
        $this->enforceRpm($tenantId, $quotas['api_rpm_limit'] ?? 60);

        // 3. Sensitive Action Quotas (Hard vs Soft Limits)
        $this->enforceUsageQuotas($request, $tenant, $quotas);

        return $next($request);
    }

    /**
     * Enforce Requests Per Minute
     */
    private function enforceRpm(string $tenantId, int $limit)
    {
        $key = 'tenant_rpm:' . $tenantId;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            abort(429, "Rate limit exceeded. Your plan ({$limit} RPM) limit has been reached.");
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Enforce usage-based quotas (e.g., API calls, AI tokens, Scraping)
     */
    private function enforceUsageQuotas(Request $request, Tenant $tenant, array $quotas)
    {
        $path = $request->path();
        $date = date('Y-m-d');
        
        // Define which routes map to which quota keys
        $quotaMap = [
            'scraper/' => 'scraping_daily_limit',
            'ai/' => 'ai_daily_limit',
            'whatsapp/send' => 'whatsapp_monthly_limit',
        ];

        foreach ($quotaMap as $pattern => $configKey) {
            if (str_contains($path, $pattern)) {
                $limit = $quotas[$configKey] ?? 0;
                if ($limit <= 0) continue;

                // Check current usage from tenant usage_stats or Cache
                $usageKey = "quota:{$tenant->tenant_id}:{$configKey}:{$date}";
                $currentUsage = (int) Cache::get($usageKey, 0);

                if ($currentUsage >= $limit) {
                    abort(403, "Quota exceeded for " . str_replace('_', ' ', $configKey) . ". Please upgrade your plan.");
                }

                // Increment usage (could be done here or in the controller after success)
                // Cache::increment($usageKey); 
            }
        }
    }
}
