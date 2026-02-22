<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // SALES REPORT
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /ecommerce/reports/sales
     * Query params: period (days, default 30), group_by (day|week|month)
     */
    public function sales(Request $request)
    {
        $period  = min((int) $request->get('period', 30), 365);
        $groupBy = in_array($request->get('group_by'), ['day', 'week', 'month']) ? $request->get('group_by') : 'day';
        $from    = now()->subDays($period);

        // Revenue trend
        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%Y-%u',
            default => '%Y-%m-%d',
        };

        $revenueTrend = DB::connection('tenant_dynamic')
            ->table('ec_orders')
            ->where('created_at', '>=', $from)
            ->whereNotIn('status', ['cancelled'])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('SUM(tax) as tax_collected'),
                DB::raw('AVG(total) as avg_order_value')
            )
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}')"))
            ->orderBy('period')
            ->get();

        // Top selling products by revenue
        $topProducts = DB::connection('tenant_dynamic')
            ->table('ec_order_items')
            ->join('ec_orders', 'ec_order_items.order_id', '=', 'ec_orders.id')
            ->where('ec_orders.created_at', '>=', $from)
            ->whereNotIn('ec_orders.status', ['cancelled'])
            ->select(
                'ec_order_items.product_name',
                'ec_order_items.sku',
                DB::raw('SUM(ec_order_items.quantity) as total_qty'),
                DB::raw('SUM(ec_order_items.subtotal) as total_revenue')
            )
            ->groupBy('ec_order_items.product_name', 'ec_order_items.sku')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Sales by category
        $byCategory = DB::connection('tenant_dynamic')
            ->table('ec_order_items')
            ->join('ec_orders', 'ec_order_items.order_id', '=', 'ec_orders.id')
            ->leftJoin('ec_products', 'ec_order_items.product_id', '=', 'ec_products.id')
            ->where('ec_orders.created_at', '>=', $from)
            ->whereNotIn('ec_orders.status', ['cancelled'])
            ->select(
                DB::raw('COALESCE(ec_products.category, "Uncategorized") as category'),
                DB::raw('SUM(ec_order_items.subtotal) as revenue'),
                DB::raw('COUNT(DISTINCT ec_orders.id) as order_count')
            )
            ->groupBy(DB::raw('COALESCE(ec_products.category, "Uncategorized")'))
            ->orderByDesc('revenue')
            ->get();

        // Profit / Loss summary (requires cost field on products)
        $profitSummary = DB::connection('tenant_dynamic')
            ->table('ec_order_items')
            ->join('ec_orders', 'ec_order_items.order_id', '=', 'ec_orders.id')
            ->leftJoin('ec_products', 'ec_order_items.product_id', '=', 'ec_products.id')
            ->where('ec_orders.created_at', '>=', $from)
            ->whereNotIn('ec_orders.status', ['cancelled'])
            ->selectRaw('
                SUM(ec_order_items.subtotal) as total_revenue,
                SUM(COALESCE(ec_products.cost, 0) * ec_order_items.quantity) as total_cogs,
                SUM(ec_order_items.subtotal) - SUM(COALESCE(ec_products.cost, 0) * ec_order_items.quantity) as gross_profit
            ')
            ->first();

        // Summary cards
        $summary = DB::connection('tenant_dynamic')
            ->table('ec_orders')
            ->where('created_at', '>=', $from)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total) as total_revenue,
                AVG(total) as avg_order_value,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = "refunded" THEN 1 END) as refunded_orders
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'period_days'    => $period,
                'group_by'       => $groupBy,
                'summary'        => $summary,
                'revenue_trend'  => $revenueTrend,
                'top_products'   => $topProducts,
                'by_category'    => $byCategory,
                'profit_summary' => $profitSummary,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // INVENTORY REPORT
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /ecommerce/reports/inventory
     */
    public function inventory(Request $request)
    {
        $deadStockDays   = (int) $request->get('dead_stock_days', 30);
        $reorderThreshold= (int) $request->get('reorder_threshold', 10);

        // Stock overview
        $overview = DB::connection('tenant_dynamic')
            ->table('ec_products')
            ->where('is_active', true)
            ->selectRaw('
                COUNT(*) as total_products,
                SUM(stock_quantity) as total_units,
                SUM(stock_quantity * COALESCE(cost, 0)) as total_stock_value,
                COUNT(CASE WHEN stock_quantity <= 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN stock_quantity > 0 AND stock_quantity <= ? THEN 1 END) as low_stock
            ', [$reorderThreshold])
            ->first();

        // Dead stock: products with stock > 0 but no sales in N days
        $deadStock = DB::connection('tenant_dynamic')
            ->table('ec_products as p')
            ->leftJoin(
                DB::raw("(SELECT product_id, MAX(ec_orders.created_at) as last_sold FROM ec_order_items 
                          JOIN ec_orders ON ec_order_items.order_id = ec_orders.id 
                          WHERE ec_orders.status != 'cancelled' GROUP BY product_id) as sales"),
                'p.id', '=', 'sales.product_id'
            )
            ->where('p.stock_quantity', '>', 0)
            ->where(function ($q) use ($deadStockDays) {
                $q->whereNull('sales.last_sold')
                  ->orWhere('sales.last_sold', '<', now()->subDays($deadStockDays));
            })
            ->select('p.id', 'p.name', 'p.sku', 'p.stock_quantity', 'p.cost', 'p.category',
                     DB::raw('COALESCE(sales.last_sold, NULL) as last_sold_at'),
                     DB::raw('p.stock_quantity * COALESCE(p.cost, 0) as tied_up_capital'))
            ->orderByDesc('tied_up_capital')
            ->limit(50)
            ->get();

        // Reorder suggestions (low stock)
        $reorderNeeded = DB::connection('tenant_dynamic')
            ->table('ec_products')
            ->where('is_active', true)
            ->where('stock_quantity', '<=', $reorderThreshold)
            ->select('id', 'name', 'sku', 'stock_quantity', 'cost', 'category')
            ->orderBy('stock_quantity')
            ->get();

        // Category stock value breakdown
        $categoryBreakdown = DB::connection('tenant_dynamic')
            ->table('ec_products')
            ->where('is_active', true)
            ->select(
                DB::raw('COALESCE(category, "Uncategorized") as category'),
                DB::raw('COUNT(*) as product_count'),
                DB::raw('SUM(stock_quantity) as total_units'),
                DB::raw('SUM(stock_quantity * COALESCE(cost, 0)) as stock_value')
            )
            ->groupBy(DB::raw('COALESCE(category, "Uncategorized")'))
            ->orderByDesc('stock_value')
            ->get();

        // Stock turnover (top moved products in last 30 days)
        $turnover = DB::connection('tenant_dynamic')
            ->table('ec_order_items')
            ->join('ec_orders', 'ec_order_items.order_id', '=', 'ec_orders.id')
            ->join('ec_products', 'ec_order_items.product_id', '=', 'ec_products.id')
            ->where('ec_orders.created_at', '>=', now()->subDays(30))
            ->whereNotIn('ec_orders.status', ['cancelled'])
            ->select(
                'ec_products.id', 'ec_products.name', 'ec_products.sku',
                'ec_products.stock_quantity',
                DB::raw('SUM(ec_order_items.quantity) as sold_qty'),
                DB::raw('SUM(ec_order_items.subtotal) as revenue')
            )
            ->groupBy('ec_products.id', 'ec_products.name', 'ec_products.sku', 'ec_products.stock_quantity')
            ->orderByDesc('sold_qty')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'overview'            => $overview,
                'category_breakdown'  => $categoryBreakdown,
                'turnover'            => $turnover,
                'dead_stock'          => $deadStock,
                'reorder_needed'      => $reorderNeeded,
                'reorder_threshold'   => $reorderThreshold,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // CUSTOMER INSIGHTS
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /ecommerce/reports/customers
     */
    public function customers(Request $request)
    {
        $period = min((int) $request->get('period', 90), 365);
        $from   = now()->subDays($period);

        // Top customers by lifetime value
        $topCustomers = DB::connection('tenant_dynamic')
            ->table('ec_customers as c')
            ->leftJoin('ec_orders as o', 'c.id', '=', 'o.customer_id')
            ->whereNotIn('o.status', ['cancelled'])
            ->select(
                'c.id', 'c.name', 'c.email',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('SUM(o.total) as lifetime_value'),
                DB::raw('AVG(o.total) as avg_order_value'),
                DB::raw('MAX(o.created_at) as last_order_at')
            )
            ->groupBy('c.id', 'c.name', 'c.email')
            ->orderByDesc('lifetime_value')
            ->limit(20)
            ->get();

        // New vs Returning customers
        $customerSegments = DB::connection('tenant_dynamic')
            ->table(DB::raw("(
                SELECT customer_id, COUNT(*) as order_count
                FROM ec_orders
                WHERE created_at >= '{$from->toDateTimeString()}'
                AND status NOT IN ('cancelled')
                GROUP BY customer_id
            ) as cust_orders"))
            ->selectRaw("
                COUNT(*) as total_customers,
                SUM(CASE WHEN order_count = 1 THEN 1 ELSE 0 END) as new_customers,
                SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as returning_customers
            ")
            ->first();

        // Customer acquisition by month
        $acquisition = DB::connection('tenant_dynamic')
            ->table('ec_customers')
            ->where('created_at', '>=', $from)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as new_customers')
            )
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month')
            ->get();

        // Summary KPIs
        $totalCustomers = DB::connection('tenant_dynamic')->table('ec_customers')->count();
        $activeCustomers = DB::connection('tenant_dynamic')
            ->table('ec_orders')
            ->where('created_at', '>=', $from)
            ->whereNotIn('status', ['cancelled'])
            ->distinct('customer_id')
            ->count('customer_id');

        $avgLtv = DB::connection('tenant_dynamic')
            ->table('ec_orders')
            ->whereNotIn('status', ['cancelled'])
            ->select('customer_id', DB::raw('SUM(total) as ltv'))
            ->groupBy('customer_id')
            ->get()
            ->avg('ltv');

        $repeatRate = $totalCustomers > 0
            ? round((($customerSegments->returning_customers ?? 0) / max($totalCustomers, 1)) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'period_days'        => $period,
                'summary'            => [
                    'total_customers'   => $totalCustomers,
                    'active_customers'  => $activeCustomers,
                    'avg_ltv'           => round((float) $avgLtv, 2),
                    'repeat_rate'       => $repeatRate,
                ],
                'segments'           => $customerSegments,
                'acquisition'        => $acquisition,
                'top_customers'      => $topCustomers,
            ],
        ]);
    }
}
