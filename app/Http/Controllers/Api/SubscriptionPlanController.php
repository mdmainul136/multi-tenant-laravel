<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionPlanController extends Controller
{
    /**
     * Get all available subscription plans
     */
    public function index()
    {
        try {
            $plans = SubscriptionPlan::active()->get();

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current tenant's subscription status
     */
    public function status(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id') 
                     ?? $request->get('token_tenant_id');

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant identification required'
                ], 400);
            }

            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();
            
            $subscription = TenantSubscription::with('plan')
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant_id' => $tenantId,
                    'subscription' => $subscription,
                    'is_active' => $subscription ? $subscription->isActive() : false,
                    'tier' => $tenant->subscription_tier ?? 'free',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subscription status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel the current subscription
     */
    public function cancel(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id')
                     ?? $request->get('token_tenant_id');

            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();
            
            $subscription = TenantSubscription::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'auto_renew' => false,
                // The subscription remains active until ends_at (end of current period)
                'ends_at' => $subscription->renews_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription canceled successfully. You will have access until ' . $subscription->ends_at->toDateString(),
                'data' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error canceling subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate a canceled subscription
     */
    public function reactivate(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id')
                     ?? $request->get('token_tenant_id');

            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();
            
            $subscription = TenantSubscription::where('tenant_id', $tenant->id)
                ->where('status', 'canceled')
                ->where('ends_at', '>', now())
                ->latest()
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No cancelable pending subscription found'
                ], 404);
            }

            $subscription->update([
                'status' => 'active',
                'canceled_at' => null,
                'auto_renew' => true,
                'ends_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription reactivated successfully',
                'data' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reactivating subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
