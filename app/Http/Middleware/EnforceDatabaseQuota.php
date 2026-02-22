<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class EnforceDatabaseQuota
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
        // Only enforce on write operations
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $tenantId = $request->header('X-Tenant-ID') ?: $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return $next($request);
        }

        $tenant = Tenant::where('tenant_id', $tenantId)->first();
        
        if (!$tenant) {
            return $next($request);
        }

        // Get latest stats and plan
        $stats = $tenant->latestDatabaseStat;
        $plan = $tenant->databasePlan;

        if (!$stats || !$plan) {
            return $next($request);
        }

        // 1. Check Storage Quota (in MB)
        $storageLimitMb = $plan->storage_limit_gb * 1024;
        if ($stats->database_size_mb >= $storageLimitMb) {
            return response()->json([
                'success' => false,
                'message' => 'Database storage quota exceeded. Please upgrade your plan to continue adding data.',
                'quota_exceeded' => true,
                'limit' => "{$plan->storage_limit_gb}GB",
                'current_usage' => $stats->database_size_mb . 'MB'
            ], 403);
        }

        // 2. Check Table Limit
        if ($plan->max_tables > 0 && $stats->table_count >= $plan->max_tables) {
            // Table limit is usually only an issue for CREATE TABLE, but we can block generic writes if we want to be strict.
            // For now, let's just warn or block only if it's a specific "create" action if we could identify it.
            // In a simple API, we'll just block any POST if they are at the limit for safety.
            if ($request->isMethod('POST')) {
                 return response()->json([
                    'success' => false,
                    'message' => "Table limit reached ({$plan->max_tables}). Please upgrade your plan.",
                    'quota_exceeded' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
