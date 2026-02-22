<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorPaymentTransaction;
use App\Models\CrossBorderIOR\IorSetting;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $now   = now();
        $month = $now->startOfMonth();

        // Orders stats
        $totalOrders   = IorForeignOrder::count();
        $pendingOrders = IorForeignOrder::where('order_status', 'pending')->count();
        $activeOrders  = IorForeignOrder::whereIn('order_status', ['sourcing', 'ordered', 'shipped', 'customs'])->count();
        $deliveredOrders = IorForeignOrder::where('order_status', 'delivered')->count();
        $thisMonthOrders = IorForeignOrder::where('created_at', '>=', $month)->count();

        // Revenue stats
        $totalRevenue = IorPaymentTransaction::where('status', 'paid')->sum('amount');
        $thisMonthRevenue = IorPaymentTransaction::where('status', 'paid')
            ->where('created_at', '>=', $month)
            ->sum('amount');

        // Status breakdown
        $statusBreakdown = IorForeignOrder::selectRaw('order_status, count(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status');

        // Payment method breakdown
        $paymentBreakdown = IorForeignOrder::selectRaw('payment_method, count(*) as count')
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->pluck('count', 'payment_method');

        // Marketplace breakdown
        $marketplaceBreakdown = IorForeignOrder::selectRaw('source_marketplace, count(*) as count')
            ->whereNotNull('source_marketplace')
            ->groupBy('source_marketplace')
            ->pluck('count', 'source_marketplace');

        // Recent orders (last 5)
        $recentOrders = IorForeignOrder::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($o) => [
                'id'            => $o->id,
                'order_number'  => $o->order_number,
                'product_name'  => $o->product_name,
                'customer_name' => $o->customer_name,
                'status'        => $o->order_status,
                'amount'        => (float) ($o->final_price_bdt ?? $o->estimated_price_bdt),
                'created_at'    => $o->created_at,
            ]);

        // Monthly Trend (Last 6 Months)
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            
            $monthlyTrend[] = [
                'month' => $date->format('M'),
                'orders' => IorForeignOrder::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'revenue' => (float) IorPaymentTransaction::where('status', 'paid')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount')
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [
                    'total'       => $totalOrders,
                    'pending'     => $pendingOrders,
                    'active'      => $activeOrders,
                    'delivered'   => $deliveredOrders,
                    'this_month'  => $thisMonthOrders,
                ],
                'revenue' => [
                    'total'      => round($totalRevenue, 2),
                    'this_month' => round($thisMonthRevenue, 2),
                    'currency'   => 'BDT',
                ],
                'status_breakdown'      => $statusBreakdown,
                'payment_breakdown'     => $paymentBreakdown,
                'marketplace_breakdown' => $marketplaceBreakdown,
                'recent_orders'         => $recentOrders,
                'monthly_trend'         => $monthlyTrend,
                'exchange_rate'         => (float) IorSetting::get('last_exchange_rate', 120),
            ],
        ]);
    }

    /**
     * GET /ior/dashboard/performance
     * Historical metrics for Recharts/Premium UI.
     */
    public function performanceMetrics(): JsonResponse
    {
        $metrics = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end   = $month->copy()->endOfMonth();

            $totalOrders = IorForeignOrder::whereBetween('created_at', [$start, $end])->count();
            $delivered   = IorForeignOrder::whereBetween('delivered_at', [$start, $end])->count();
            
            $metrics[] = [
                'name'      => $month->format('M Y'),
                'orders'    => $totalOrders,
                'delivered' => $delivered,
                'success_rate' => $totalOrders > 0 ? round(($delivered / $totalOrders) * 100, 1) : 100,
                // Mocking some other premium metrics for the UI
                'landed_cost_accuracy' => 98.5,
                'proxy_uptime' => 99.9,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $metrics
        ]);
    }
}



