<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IorScraperDashboardController extends Controller
{
    /**
     * Get management schema and stats for the IOR Sourcing Dashboard.
     */
    public function index()
    {
        $settings = DB::table('ior_scraper_settings')->where('is_active', true)->first();
        
        $stats = [
            'total_scrapes' => DB::table('ior_scraper_logs')->count(),
            'success_rate'  => $this->calculateSuccessRate(),
            'total_cost'    => DB::table('ior_scraper_logs')->sum('cost'),
            'budget_usage'  => [
                'current' => (float) ($settings->current_monthly_spend ?? 0),
                'limit'   => (float) ($settings->monthly_budget_cap ?? 100),
                'percent' => $this->calculateBudgetPercent($settings),
            ],
            'by_provider'   => $this->getStatsByProvider(),
            'proxy_status' => [
                'enabled' => (bool) ($settings->use_proxy ?? false),
                'is_active' => (bool) (($settings->use_proxy ?? false) && ($settings->proxy_expires_at === null || now()->lt($settings->proxy_expires_at))),
                'expiry' => $settings->proxy_expires_at ?? null,
            ],
            'exchange_rate_safety' => [
                'base_rate' => (float) ($settings->base_exchange_rate ?? 120.0),
                'buffer_percent' => (float) ($settings->exchange_buffer_percent ?? 2.0),
                'effective_rate' => round(($settings->base_exchange_rate ?? 120.0) * (1 + (($settings->exchange_buffer_percent ?? 2.0) / 100)), 2),
            ],
            'marketplace_health' => DB::table('ior_scraper_health_stats')->get(),
            'recent_logs'   => DB::table('ior_scraper_logs')->orderByDesc('created_at')->limit(10)->get(),
        ];

        $schema = [
            'providers' => [
                'python' => [
                    'label' => 'Internal Scraper (Python)',
                    'capabilities' => ['gallery', 'reviews', 'stock_tracking', 'proxy_aware'],
                    'cost_per_run' => 0.001,
                ],
                // ... same as before
            ],
            'actions' => [
                'trigger_sync' => [
                    'endpoint' => '/api/ior/sync',
                    'method' => 'POST',
                    'params' => ['ids', 'provider', 'queue'],
                ],
                'purchase_proxy' => [
                    'endpoint' => '/api/ior/purchase-proxy',
                    'method' => 'POST',
                    'params' => ['type', 'duration'],
                ],
                'update_proxy_settings' => [
                    'endpoint' => '/api/ior/settings/proxy',
                    'method' => 'PATCH',
                    'params' => ['host', 'port', 'user', 'password'],
                ],
                'recalculate_prices' => [
                    'endpoint' => '/api/ior/recalculate-prices',
                    'method' => 'POST',
                    'params' => ['ids'],
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'schema'  => $schema,
        ]);
    }

    /**
     * Purchase (Enable) proxy for the tenant.
     */
    public function purchaseProxy(Request $request)
    {
        $request->validate([
            'type' => 'required|in:shared,dedicated',
            'duration_months' => 'required|integer|min:1',
        ]);

        $settings = DB::table('ior_scraper_settings')->first();
        if (!$settings) return response()->json(['success' => false, 'error' => 'Settings not found'], 404);

        // Simulated billing check
        $cost = $request->type === 'dedicated' ? 25.0 : 10.0;
        $totalCost = $cost * $request->duration_months;

        DB::table('ior_scraper_settings')->where('id', $settings->id)->update([
            'use_proxy' => true,
            'proxy_type' => $request->type,
            'proxy_expires_at' => now()->addMonths($request->duration_months),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Proxy {$request->type} activated until " . now()->addMonths($request->duration_months)->toDateString(),
            'cost' => $totalCost
        ]);
    }

    /**
     * Update proxy configuration details.
     */
    public function updateProxySettings(Request $request)
    {
        $data = $request->validate([
            'host' => 'nullable|string',
            'port' => 'nullable|string',
            'user' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        DB::table('ior_scraper_settings')->update([
            'proxy_host' => $data['host'],
            'proxy_port' => $data['port'],
            'proxy_user' => $data['user'],
            'proxy_password' => $data['password'],
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Proxy settings updated']);
    }

    private function calculateBudgetPercent($settings): float
    {
        if (!$settings || $settings->monthly_budget_cap <= 0) return 0.0;
        return round(($settings->current_monthly_spend / $settings->monthly_budget_cap) * 100, 2);
    }

    private function calculateSuccessRate(): float
    {
        $total = DB::table('ior_scraper_logs')->count();
        if ($total === 0) return 100.0;

        $success = DB::table('ior_scraper_logs')->where('status', 'success')->count();
        return round(($success / $total) * 100, 2);
    }

    private function getStatsByProvider(): array
    {
        return DB::table('ior_scraper_logs')
            ->select('provider', DB::raw('count(*) as count'), DB::raw('sum(cost) as total_cost'))
            ->groupBy('provider')
            ->get()
            ->toArray();
    }

    /**
     * Test a URL in real-time without saving to catalog.
     */
    public function debugScrape(Request $request)
    {
        $request->validate(['url' => 'required|url']);
        
        $python = app(\App\Modules\CrossBorderIOR\Services\PythonScraperService::class);
        $settings = DB::table('ior_scraper_settings')->first();
        
        try {
            $result = $python->scrapeProduct($request->url, $settings->tenant_id ?? null);
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
