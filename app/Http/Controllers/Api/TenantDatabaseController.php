<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDatabasePlan;
use App\Services\TenantDatabaseAnalyticsService;
use App\Services\TenantDatabaseIsolationService;
use Illuminate\Http\Request;

class TenantDatabaseController extends Controller
{
    protected TenantDatabaseAnalyticsService $analyticsService;
    protected TenantDatabaseIsolationService $isolationService;

    public function __construct(
        TenantDatabaseAnalyticsService $analyticsService,
        TenantDatabaseIsolationService $isolationService
    ) {
        $this->analyticsService = $analyticsService;
        $this->isolationService = $isolationService;
    }

    /**
     * Get database analytics (usage, quota, overview).
     *
     * GET /api/database/analytics
     */
    public function analytics(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id')
                ?? $request->input('token_tenant_id');

            $tenant = Tenant::where('tenant_id', $tenantId)
                ->with(['databasePlan', 'latestDatabaseStat'])
                ->firstOrFail();

            $analytics = $this->analyticsService->getAnalytics($tenant);
            $quotaCheck = $this->isolationService->checkQuotaUsage($tenant);

            return response()->json([
                'success' => true,
                'data' => [
                    'usage' => $analytics['usage'],
                    'quota' => $analytics['quota'],
                    'alerts' => $this->buildAlerts($quotaCheck),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch database analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get per-table breakdown.
     *
     * GET /api/database/tables
     */
    public function tables(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id')
                ?? $request->input('token_tenant_id');

            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $tables = $this->analyticsService->getTableBreakdown($tenant);

            return response()->json([
                'success' => true,
                'data' => [
                    'tables' => $tables,
                    'total_tables' => count($tables),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch table breakdown',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get historical growth trend.
     *
     * GET /api/database/growth?days=30
     */
    public function growth(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id')
                ?? $request->input('token_tenant_id');

            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $days = (int) $request->get('days', 30);
            $days = max(1, min($days, 365)); // Clamp between 1 and 365

            $growth = $this->analyticsService->getGrowthTrend($tenant, $days);

            return response()->json([
                'success' => true,
                'data' => $growth,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch growth trend',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get available database plans.
     *
     * GET /api/database/plans
     */
    public function plans()
    {
        try {
            $plans = TenantDatabasePlan::active()
                ->orderBy('storage_limit_gb', 'asc')
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'storage_limit_gb' => $plan->storage_limit_gb,
                        'max_tables' => $plan->max_tables,
                        'max_connections' => $plan->max_connections,
                        'price' => (float) $plan->price,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plans,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch database plans',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Build alert messages based on quota usage.
     */
    private function buildAlerts(array $quotaCheck): array
    {
        $alerts = [];

        if ($quotaCheck['over_quota']) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "You have exceeded your storage quota! Current usage: {$quotaCheck['usage_percent']}%. Please upgrade your plan.",
            ];
        } elseif ($quotaCheck['usage_percent'] >= 90) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "You are using {$quotaCheck['usage_percent']}% of your storage quota. Consider upgrading your plan.",
            ];
        } elseif ($quotaCheck['usage_percent'] >= 75) {
            $alerts[] = [
                'type' => 'info',
                'message' => "You are using {$quotaCheck['usage_percent']}% of your storage quota.",
            ];
        }

        return $alerts;
    }
}
