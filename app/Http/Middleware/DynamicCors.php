<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\TenantDomain;

/**
 * Handles CORS for tenant subdomains and custom domains dynamically.
 *
 * Priority order:
 * 1. Static allowed_origins from config/cors.php (handled by Laravel's built-in HandleCors)
 * 2. Subdomain patterns (*.localhost, *.zosair.com) — already in cors.php patterns
 * 3. Tenant custom domains from database — handled HERE
 */
class DynamicCors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin');

        if (!$origin) {
            return $next($request);
        }

        // Check if origin is allowed
        if ($this->isAllowed($origin)) {
            $response = $next($request);

            // Add CORS headers
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-Frontend-API-Key, X-Tenant-Key, Accept');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');

            // Handle preflight
            if ($request->getMethod() === 'OPTIONS') {
                $response->setStatusCode(204);
            }

            return $response;
        }

        return $next($request);
    }

    private function isAllowed(string $origin): bool
    {
        // Parse the hostname from origin (strip protocol and port)
        $parsed = parse_url($origin);
        $host = $parsed['host'] ?? '';

        if (!$host) return false;

        // 1. Check static patterns from config (subdomain patterns)
        $staticAllowed = config('cors.allowed_origins', []);
        if (in_array($origin, $staticAllowed)) {
            return true;
        }

        // 2. Check regex patterns from config
        $patterns = config('cors.allowed_origins_patterns', []);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        // 3. Check tenant custom domains from DB (cached for 10 minutes)
        return $this->isCustomDomain($host);
    }

    private function isCustomDomain(string $host): bool
    {
        // Strip port from host if present
        $host = strtolower(explode(':', $host)[0]);

        // Skip localhost / IPs — already handled by static config
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Skip our own base domains
        $baseDomains = ['zosair.com', 'zosair.net', 'zosair.org', 'afrosafashion.com', 'lvh.me'];
        foreach ($baseDomains as $base) {
            if ($host === $base || str_ends_with($host, '.' . $base)) {
                return true; // Subdomains of our base domains are allowed
            }
        }

        // Check database for custom domain
        try {
            $cacheKey = 'tenant_domain_' . md5($host);

            return Cache::remember($cacheKey, 600, function () use ($host) {
                return TenantDomain::where('domain', $host)
                    ->where('status', 'verified')
                    ->exists();
            });
        } catch (\Exception $e) {
            // If DB is unavailable, deny
            return false;
        }
    }
}
