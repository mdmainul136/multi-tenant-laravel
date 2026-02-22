<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods for tenant
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $paymentMethods = PaymentMethod::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($pm) {
                    return [
                        'id' => $pm->id,
                        'type' => $pm->type,
                        'brand' => $pm->brand,
                        'last4' => $pm->last4,
                        'exp_month' => $pm->exp_month,
                        'exp_year' => $pm->exp_year,
                        'expiry_display' => $pm->expiry_display,
                        'display_name' => $pm->display_name,
                        'is_default' => $pm->is_default,
                        'is_expired' => $pm->isExpired(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $paymentMethods
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new payment method from Stripe
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'stripe_payment_method_id' => 'required|string',
            ]);

            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            // Get payment method details from Stripe
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $stripePaymentMethod = \Stripe\PaymentMethod::retrieve($request->stripe_payment_method_id);

            // Check if payment method already exists by Stripe ID
            $existingById = PaymentMethod::where('stripe_payment_method_id', $request->stripe_payment_method_id)->first();
            if ($existingById) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method already exists'
                ], 400);
            }

            // Better check: Is there already an active card with the same details for this tenant?
            $brand = strtolower($stripePaymentMethod->card->brand ?? '');
            $last4 = $stripePaymentMethod->card->last4 ?? '';
            $expMonth = $stripePaymentMethod->card->exp_month ?? null;
            $expYear = $stripePaymentMethod->card->exp_year ?? null;

            $existingByDetails = PaymentMethod::where('tenant_id', $tenant->id)
                ->where('brand', $brand)
                ->where('last4', $last4)
                ->where('exp_month', $expMonth)
                ->where('exp_year', $expYear)
                ->where('is_active', true)
                ->first();

            if ($existingByDetails) {
                // Update the Stripe PM ID to the latest one
                $existingByDetails->update(['stripe_payment_method_id' => $stripePaymentMethod->id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Payment method updated successfully',
                    'data' => [
                        'id' => $existingByDetails->id,
                        'display_name' => $existingByDetails->display_name,
                        'is_default' => $existingByDetails->is_default,
                    ]
                ]);
            }

            // Create new payment method record
            $paymentMethod = PaymentMethod::create([
                'tenant_id' => $tenant->id,
                'stripe_payment_method_id' => $stripePaymentMethod->id,
                'type' => $stripePaymentMethod->type,
                'brand' => $brand,
                'last4' => $last4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'is_default' => false,
                'is_active' => true,
            ]);

            // If this is the first payment method, set as default
            $count = PaymentMethod::where('tenant_id', $tenant->id)->count();
            if ($count === 1) {
                $paymentMethod->setAsDefault();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => [
                    'id' => $paymentMethod->id,
                    'display_name' => $paymentMethod->display_name,
                    'is_default' => $paymentMethod->is_default,
                ]
            ]);

        } catch (\Stripe\Exception\CardException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Card error: ' . $e->getMessage(),
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request: ' . $e->getMessage(),
            ], 400);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Network error connecting to payment provider.',
            ], 502);
        } catch (\Exception $e) {
            Log::error('Failed to add payment method: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment method. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove payment method
     */
    public function destroy(Request $request, $id)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $wasDefault = $paymentMethod->is_default;

            // Detach from Stripe (optional)
            // \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            // \Stripe\PaymentMethod::retrieve($paymentMethod->stripe_payment_method_id)->detach();

            // Soft delete by marking as inactive
            $paymentMethod->update(['is_active' => false]);

            // If this was default, set another as default
            if ($wasDefault) {
                $newDefault = PaymentMethod::where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->where('id', '!=', $id)
                    ->first();

                if ($newDefault) {
                    $newDefault->setAsDefault();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove payment method: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set payment method as default
     */
    public function setDefault(Request $request, $id)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->firstOrFail();

            $paymentMethod->setAsDefault();

            return response()->json([
                'success' => true,
                'message' => 'Default payment method updated'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to set default payment method: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
