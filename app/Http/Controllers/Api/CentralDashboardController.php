<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Models from various modules
use App\Models\Ecommerce\Order;
use App\Models\Ecommerce\Customer;
use App\Models\Ecommerce\Product;
use App\Models\HRM\Staff;
use App\Models\HRM\Attendance;
use App\Models\HRM\LeaveRequest;
use App\Models\Finance\Account;
use App\Models\Finance\Transaction;
use App\Models\Tracking\TrackingContainer;
use App\Models\Tracking\TrackingEventLog;

class CentralDashboardController extends Controller
{
    public function __construct(protected ModuleService $moduleService) {}

    /**
     * GET /api/dashboard/summary
     * Unified dashboard: stats for each active module + recommended modules.
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id')
                 ?? $request->header('X-Tenant-ID');

        $tenant = Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Get all active module keys for this tenant
        $activeModules = TenantModule::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->with('module')
            ->get();

        $activeKeys = $activeModules
            ->map(fn ($tm) => optional($tm->module)->module_key)
            ->filter()
            ->values()
            ->toArray();

        $summary = [];

        // ── Module Widgets ──────────────────────────────────────────────

        // 1. Ecommerce
        if (in_array('ecommerce', $activeKeys)) {
            try {
                $summary['ecommerce'] = [
                    'total_sales'    => (float) Order::where('status', 'completed')->sum('total_amount'),
                    'order_count'    => Order::count(),
                    'customer_count' => Customer::count(),
                    'product_count'  => Product::count(),
                    'pending_orders' => Order::where('status', 'pending')->count(),
                ];
            } catch (\Exception $e) {
                $summary['ecommerce'] = ['error' => 'Module data unavailable'];
            }
        }

        // 2. HRM
        if (in_array('hrm', $activeKeys)) {
            try {
                $summary['hrm'] = [
                    'total_staff'     => Staff::count(),
                    'present_today'   => Attendance::whereDate('date', now())->count(),
                    'pending_leaves'  => LeaveRequest::where('status', 'pending')->count(),
                ];
            } catch (\Exception $e) {
                $summary['hrm'] = ['error' => 'Module data unavailable'];
            }
        }

        // 3. Finance
        if (in_array('finance', $activeKeys)) {
            try {
                $income = Transaction::join('ec_finance_ledgers', 'ec_finance_transactions.id', '=', 'ec_finance_ledgers.transaction_id')
                    ->join('ec_finance_accounts', 'ec_finance_ledgers.account_id', '=', 'ec_finance_accounts.id')
                    ->where('ec_finance_accounts.type', 'income')
                    ->whereDate('ec_finance_transactions.date', now())
                    ->sum('ec_finance_ledgers.amount');

                $expense = Transaction::join('ec_finance_ledgers', 'ec_finance_transactions.id', '=', 'ec_finance_ledgers.transaction_id')
                    ->join('ec_finance_accounts', 'ec_finance_ledgers.account_id', '=', 'ec_finance_accounts.id')
                    ->where('ec_finance_accounts.type', 'expense')
                    ->whereDate('ec_finance_transactions.date', now())
                    ->sum('ec_finance_ledgers.amount');

                $summary['finance'] = [
                    'today_income'  => (float) $income,
                    'today_expense' => (float) $expense,
                    'cash_balance'  => (float) Account::where('type', 'asset')->sum('balance'),
                ];
            } catch (\Exception $e) {
                $summary['finance'] = ['error' => 'Module data unavailable'];
            }
        }

        // 4. Tracking
        if (in_array('tracking', $activeKeys)) {
            try {
                $summary['tracking'] = [
                    'total_events_24h'  => TrackingEventLog::where('created_at', '>=', now()->subDay())->count(),
                    'active_containers' => TrackingContainer::where('is_active', true)->count(),
                ];
            } catch (\Exception $e) {
                $summary['tracking'] = ['error' => 'Module data unavailable'];
            }
        }

        // 5. CRM
        if (in_array('crm', $activeKeys)) {
            try {
                $summary['crm'] = [
                    'total_contacts' => DB::connection('tenant_dynamic')->table('crm_contacts')->count(),
                    'total_deals'    => DB::connection('tenant_dynamic')->table('crm_deals')->count(),
                    'open_deals'     => DB::connection('tenant_dynamic')->table('crm_deals')->where('stage', '!=', 'closed_won')->where('stage', '!=', 'closed_lost')->count(),
                    'total_tasks'    => DB::connection('tenant_dynamic')->table('crm_tasks')->count(),
                ];
            } catch (\Exception $e) {
                $summary['crm'] = ['error' => 'Module data unavailable'];
            }
        }

        // 6. Inventory
        if (in_array('inventory', $activeKeys)) {
            try {
                $summary['inventory'] = [
                    'total_products'   => DB::connection('tenant_dynamic')->table('inventory_products')->count(),
                    'low_stock_items'  => DB::connection('tenant_dynamic')->table('inventory_products')->whereColumn('quantity', '<=', 'reorder_level')->count(),
                    'total_warehouses' => DB::connection('tenant_dynamic')->table('warehouses')->count(),
                ];
            } catch (\Exception $e) {
                $summary['inventory'] = ['error' => 'Module data unavailable'];
            }
        }

        // 7. POS
        if (in_array('pos', $activeKeys)) {
            try {
                $summary['pos'] = [
                    'today_sales' => (float) DB::connection('tenant_dynamic')->table('pos_sessions')
                        ->whereDate('opened_at', now())
                        ->sum('total_sales'),
                    'open_sessions' => DB::connection('tenant_dynamic')->table('pos_sessions')
                        ->whereNull('closed_at')
                        ->count(),
                ];
            } catch (\Exception $e) {
                $summary['pos'] = ['error' => 'Module data unavailable'];
            }
        }

        // 8. Marketing
        if (in_array('marketing', $activeKeys)) {
            try {
                $summary['marketing'] = [
                    'total_campaigns' => DB::connection('tenant_dynamic')->table('marketing_campaigns')->count(),
                    'active_campaigns'=> DB::connection('tenant_dynamic')->table('marketing_campaigns')->where('status', 'active')->count(),
                ];
            } catch (\Exception $e) {
                $summary['marketing'] = ['error' => 'Module data unavailable'];
            }
        }

        // 9. Loyalty
        if (in_array('loyalty', $activeKeys)) {
            try {
                $summary['loyalty'] = [
                    'total_members' => DB::connection('tenant_dynamic')->table('loyalty_members')->count(),
                    'active_rewards'=> DB::connection('tenant_dynamic')->table('loyalty_rewards')->where('is_active', true)->count(),
                ];
            } catch (\Exception $e) {
                $summary['loyalty'] = ['error' => 'Module data unavailable'];
            }
        }

        // ── Meta Information ────────────────────────────────────────────

        // Active modules list
        $activeModulesList = $activeModules->map(function ($tm) {
            $conf = config("modules.{$tm->module->module_key}", []);
            return [
                'module_key'  => $tm->module->module_key,
                'module_name' => $tm->module->module_name,
                'status'      => $tm->status,
                'expires_at'  => $tm->expires_at,
                'icon'        => $conf['icon'] ?? 'box',
                'color'       => $conf['color'] ?? '#6366f1',
            ];
        })->values();

        // Recommended modules
        $recommended = $this->moduleService->getRecommendedModules($tenantId);

        // Recent Activity Feed
        $recentActivity = $this->getRecentActivity($activeKeys);

        return response()->json([
            'success' => true,
            'data' => [
                'widgets'             => $summary,
                'active_modules'      => $activeModulesList,
                'recommended_modules' => $recommended,
                'recent_activity'     => $recentActivity,
                'tenant' => [
                    'tenant_id'     => $tenant->tenant_id,
                    'tenant_name'   => $tenant->tenant_name,
                    'company_name'  => $tenant->company_name,
                    'business_type' => $tenant->business_type,
                    'country'       => $tenant->country,
                    'domain'        => $tenant->domain,
                ],
            ],
            'timestamp' => now(),
        ]);
    }

    /**
     * Aggregates recent activities from all active modules.
     */
    private function getRecentActivity(array $activeKeys): array
    {
        $activities = collect();

        if (in_array('ecommerce', $activeKeys)) {
            try {
                Order::latest()->limit(5)->get()->each(function ($order) use ($activities) {
                    $activities->push([
                        'module'  => 'ecommerce',
                        'type'    => 'new_order',
                        'message' => "New order #{$order->order_number} received",
                        'amount'  => $order->total_amount,
                        'time'    => $order->created_at,
                    ]);
                });
            } catch (\Exception $e) {}
        }

        if (in_array('hrm', $activeKeys)) {
            try {
                Attendance::latest()->limit(5)->get()->each(function ($attendance) use ($activities) {
                    $activities->push([
                        'module'  => 'hrm',
                        'type'    => 'attendance',
                        'message' => 'Staff attendance recorded',
                        'time'    => $attendance->created_at,
                    ]);
                });
            } catch (\Exception $e) {}
        }

        if (in_array('finance', $activeKeys)) {
            try {
                Transaction::latest()->limit(5)->get()->each(function ($trx) use ($activities) {
                    $activities->push([
                        'module'  => 'finance',
                        'type'    => 'transaction',
                        'message' => "Financial transaction: {$trx->description}",
                        'amount'  => $trx->amount,
                        'time'    => $trx->date,
                    ]);
                });
            } catch (\Exception $e) {}
        }

        return $activities->sortByDesc('time')->values()->take(15)->all();
    }
}

