<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Product;
use App\Models\Ecommerce\Order;
use App\Models\Ecommerce\Customer;
use Illuminate\Http\Request;

class EcommerceDashboardController extends Controller
{
    /**
     * Get overview statistics for the ecommerce dashboard
     */
    public function stats()
    {
        try {
            $productCount  = Product::count();
            $orderCount    = Order::count();
            $customerCount = Customer::count();
            $totalRevenue  = Order::where('payment_status', 'paid')->sum('total');

            $recentOrders = Order::with('customer')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($o) => [
                    'id'            => $o->id,
                    'customer_name' => $o->customer?->name ?? $o->customer_name ?? 'Guest',
                    'total'         => $o->total,
                    'status'        => $o->status,
                    'created_at'    => $o->created_at,
                ]);

            // IOR Bridge: Add cross-border stats if the module exists
            $iorStats = [];
            if (class_exists(\App\Models\CrossBorderIOR\IorForeignOrder::class)) {
                $iorStats = [
                    'total_orders'   => \App\Models\CrossBorderIOR\IorForeignOrder::count(),
                    'pending_orders' => \App\Models\CrossBorderIOR\IorForeignOrder::where('order_status', 'pending')->count(),
                    'total_revenue'  => \App\Models\CrossBorderIOR\IorPaymentTransaction::where('status', 'paid')->sum('amount'),
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_products'  => $productCount,
                    'total_orders'    => $orderCount,
                    'total_customers' => $customerCount,
                    'total_revenue'   => number_format($totalRevenue, 2, '.', ''),
                    'recent_orders'   => $recentOrders,
                    'ior_overview'    => $iorStats,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching ecommerce stats',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}



