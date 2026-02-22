<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\DatabaseManager;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    protected DatabaseManager $databaseManager;

    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Identification Layer 1: API Key (Strong Identification)
        $apiKey = $request->header('X-Frontend-API-Key') ?: $request->header('X-Tenant-Key');
        
        if ($apiKey) {
            $tenant = Tenant::where('api_key', $apiKey)
                ->where('status', 'active')
                ->first();
            
            if ($tenant) {
                $tenantId = $tenant->tenant_id;
                \Illuminate\Support\Facades\Log::info("IdentifyTenant: Found tenant by API Key: $tenantId");
            }
        }

        // Identification Layer 2: X-Tenant-ID Header
        if (!$tenantId) {
            $tenantId = $request->header('X-Tenant-ID');
        }

        \Illuminate\Support\Facades\Log::info("IdentifyTenant: Start identification for host: " . $request->getHost());

        if (!$tenantId) {
            $host = $request->getHost();
            \Illuminate\Support\Facades\Log::info("IdentifyTenant: Attempting hostname-based discovery for: $host");

            // 1. Check if it's a custom domain in our records
            // We use a verified domain lookup which is highly performant
            $customDomain = TenantDomain::where('domain', $host)
                ->where('status', 'verified')
                ->first();

            if ($customDomain) {
                $tenantId = $customDomain->tenant_id;
                \Illuminate\Support\Facades\Log::info("IdentifyTenant: Found custom domain mapping: $tenantId");
            } else {
                // 2. Extract from subdomain (e.g., tenant1.example.com)
                $appUrl = config('app.url');
                $baseDomain = parse_url($appUrl, PHP_URL_HOST);
                
                if ($host !== $baseDomain && str_ends_with($host, "." . $baseDomain)) {
                    $tenantId = str_replace("." . $baseDomain, "", $host);
                    \Illuminate\Support\Facades\Log::info("IdentifyTenant: Extracted subdomain: $tenantId");
                } else {
                    // Fallback for direct host matching (e.g. acme33.localhost)
                    if ($host !== $baseDomain && $host !== 'localhost') {
                        $parts = explode('.', $host);
                        $tenantId = $parts[0];
                        \Illuminate\Support\Facades\Log::info("IdentifyTenant: Fallback to first segment of DNS: $tenantId");
                    }
                }
            }
        }

        if (!$tenantId) {
            \Illuminate\Support\Facades\Log::warning("IdentifyTenant: No tenant ID found for host: " . $request->getHost());
            return response()->json([
                'success' => false,
                'message' => 'Tenant identification required. Please provide X-Tenant-ID header or use tenant subdomain.',
            ], 400);
        }

        \Illuminate\Support\Facades\Log::info("IdentifyTenant: Final tenantId to search: $tenantId");

        // Validate tenant exists and is active
        // Only fetch if we haven't already found it via API Key
        if (!isset($tenant)) {
            $tenant = Tenant::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
        }

        // If still not found, it might be that the tenant_id in the DB is different from the host,
        // but the host was used as a fallback. Re-check verified domains just in case.
        if (!$tenant) {
            $customDomain = TenantDomain::where('domain', $tenantId)
                ->where('status', 'verified')
                ->first();
            if ($customDomain) {
                $tenant = Tenant::where('tenant_id', $customDomain->tenant_id)
                    ->where('status', 'active')
                    ->first();
                if ($tenant) {
                    $tenantId = $tenant->tenant_id;
                }
            }
        }

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found or inactive',
            ], 404);
        }

        // KILL-SWITCH SAFETY: Block requests if tenant is suspended, billing failed, or terminated
        if ($tenant->status !== 'active') {
            $message = 'Tenant access suspended';
            if ($tenant->status === 'terminated') $message = 'Tenant access terminated';
            if ($tenant->status === 'billing_failed') $message = 'Tenant access suspended due to billing failure';

            return response()->json([
                'success' => false,
                'message' => $message,
                'status' => $tenant->status,
            ], 403);
        }

        // Switch to tenant database
        try {
            $this->databaseManager->switchToTenantDatabase($tenantId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error switching to tenant database',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        // --- REGIONAL STRATEGY & DEFAULTS ---
        $regions = config('tenant_regions.regions', []);
        $tenantRegion = $tenant->region ?: 'global'; // fallback to global
        
        // Find region config by key or by country mapping
        if (!$tenant->region && $tenant->country) {
            foreach ($regions as $key => $config) {
                if (isset($config['countries']) && in_array($tenant->country, $config['countries'])) {
                    $tenantRegion = $key;
                    break;
                }
            }
        }

        $regionConfig = $regions[$tenantRegion] ?? $regions['global'];
        
        // Set global app config for regional awareness
        config([
            'app.tenant_region' => $tenantRegion,
            'app.currency' => $regionConfig['currency'] ?? 'USD',
            'app.locale' => $regionConfig['locale'] ?? 'en',
            'app.timezone' => $regionConfig['timezone'] ?? 'UTC',
        ]);

        // Attach tenant info to request
        $request->attributes->set('tenant_id', $tenant->tenant_id);
        $request->attributes->set('tenant_region', $tenantRegion);
        
        $request->merge([
            'tenant' => [
                'id' => $tenant->tenant_id,
                'name' => $tenant->tenant_name,
                'database' => $tenant->database_name,
                'region' => $tenantRegion,
                'currency' => config('app.currency'),
                'created_at' => $tenant->created_at,
            ],
        ]);


        return $next($request);
    }
}
