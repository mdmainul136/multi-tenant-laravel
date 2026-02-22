<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function sales(Request $request)
    {
        $days = (int) $request->get('days', 30);

        $revenueTrend = DB::table('ec_orders')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('status', ['completed', 'delivered'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $topProducts = DB::table('ec_order_items')
            ->join('ec_products', 'ec_order_items.product_id', '=', 'ec_products.id')
            ->join('ec_orders', 'ec_order_items.order_id', '=', 'ec_orders.id')
            ->where('ec_orders.created_at', '>=', now()->subDays($days))
            ->whereIn('ec_orders.status', ['completed', 'delivered'])
            ->selectRaw('ec_products.id, ec_products.name, SUM(ec_order_items.quantity) as units_sold, SUM(ec_order_items.subtotal) as revenue')
            ->groupBy('ec_products.id', 'ec_products.name')
            ->orderByDesc('revenue')->limit(10)->get();

        $categorySplit = DB::table('ec_order_items')
            ->join('ec_products', 'ec_order_items.product_id', '=', 'ec_products.id')
            ->join('ec_categories', 'ec_products.category_id', '=', 'ec_categories.id')
            ->join('ec_orders', 'ec_order_items.order_id', '=', 'ec_orders.id')
            ->where('ec_orders.created_at', '>=', now()->subDays($days))
            ->selectRaw('ec_categories.name as category, SUM(ec_order_items.subtotal) as revenue')
            ->groupBy('ec_categories.name')
            ->orderByDesc('revenue')->get();

        return response()->json([
            'success' => true,
            'data'    => compact('revenueTrend', 'topProducts', 'categorySplit'),
        ]);
    }

    public function inventory(Request $request)
    {
        $deadStock = DB::table('ec_products')
            ->leftJoin('ec_order_items', function ($j) {
                $j->on('ec_products.id', '=', 'ec_order_items.product_id')
                  ->whereRaw('ec_order_items.created_at >= ?', [now()->subDays(90)]);
            })
            ->whereNull('ec_order_items.id')
            ->where('ec_products.stock_quantity', '>', 0)
            ->select('ec_products.id', 'ec_products.name', 'ec_products.stock_quantity', 'ec_products.cost')
            ->get();

        $reorder = DB::table('ec_products')
            ->whereRaw('stock_quantity <= reorder_level')
            ->where('is_active', 1)
            ->select('id', 'name', 'stock_quantity', 'reorder_level')
            ->orderBy('stock_quantity')
            ->get();

        $categoryBreakdown = DB::table('ec_products')
            ->join('ec_categories', 'ec_products.category_id', '=', 'ec_categories.id')
            ->selectRaw('ec_categories.name as category, COUNT(ec_products.id) as sku_count, SUM(ec_products.stock_quantity) as total_stock, SUM(ec_products.stock_quantity * ec_products.cost) as stock_value')
            ->groupBy('ec_categories.name')
            ->orderByDesc('stock_value')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => compact('deadStock', 'reorder', 'categoryBreakdown'),
        ]);
    }

    public function customers(Request $request)
    {
        $days = (int) $request->get('days', 90);

        $topByLtv = DB::table('ec_customers')
            ->select('id', 'name', 'email', 'total_orders', 'total_spent')
            ->where('is_active', true)
            ->orderByDesc('total_spent')
            ->limit(10)->get();

        $acquisitionTrend = DB::table('ec_customers')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as new_customers')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
            ->orderBy('month')->get();

        $repeatRate = DB::table('ec_customers')
            ->selectRaw('COUNT(*) as total_customers, SUM(CASE WHEN total_orders > 1 THEN 1 ELSE 0 END) as repeat_customers')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => compact('topByLtv', 'acquisitionTrend', 'repeatRate'),
        ]);
    }
}
