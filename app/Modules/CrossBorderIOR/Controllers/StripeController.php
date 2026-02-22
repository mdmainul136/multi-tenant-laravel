<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function __construct(private StripePaymentService $stripe) {}

    // ══════════════════════════════════════════
    // INITIATE — authenticated
    // ══════════════════════════════════════════

    /**
     * POST /ior/payment/stripe/initiate
     * Create a Stripe Checkout Session and return redirect URL.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'     => 'required|integer|exists:ior_foreign_orders,id',
            'payment_type' => 'in:advance,remaining,full',
        ]);

        if (!$this->stripe->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe not configured. Set STRIPE_SECRET_KEY in .env or IOR Settings.',
            ], 503);
        }

        $order       = IorForeignOrder::findOrFail($request->integer('order_id'));
        $paymentType = $request->input('payment_type', 'advance');

        $amount = match ($paymentType) {
            'remaining' => $order->remaining_amount,
            'full'      => $order->final_price_bdt ?? $order->estimated_price_bdt,
            default     => $order->advance_amount,
        };

        if (!$amount || $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid payment amount.'], 422);
        }

        try {
            $result = $this->stripe->createCheckoutSession($order, $amount, $paymentType);

            return response()->json([
                'success'      => true,
                'checkout_url' => $result['checkout_url'],
                'session_id'   => $result['session_id'],
                'amount'       => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('[IOR Stripe] Initiate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════
    // WEBHOOK — public, no auth
    // ══════════════════════════════════════════

    /**
     * POST /ior/payment/stripe/webhook
     * Stripe sends this on payment_intent.succeeded, checkout.session.completed etc.
     * This MUST be excluded from CSRF and body parsing middleware.
     */
    public function webhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            $result = $this->stripe->handleWebhook($payload, $sigHeader);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('[IOR Stripe] Webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ══════════════════════════════════════════
    // SUCCESS / CANCEL redirects — public
    // ══════════════════════════════════════════

    /**
     * GET /api/ior/payment/stripe/success
     * Browser redirect from Stripe after successful payment.
     * Verifies session with Stripe API before showing success.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        $orderId   = $request->query('order_id');

        if (!$sessionId) {
            return response()->json(['success' => false, 'message' => 'Missing session_id'], 400);
        }

        try {
            $session = $this->stripe->getSession($sessionId);
            $paid    = ($session['payment_status'] ?? '') === 'paid';

            // Redirect to frontend
            $frontendUrl = env('FRONTEND_URL', '/');
            $redirectTo  = $frontendUrl . ($paid
                ? "?ior_payment=success&order_id={$orderId}"
                : "?ior_payment=pending&order_id={$orderId}");

            return redirect()->away($redirectTo);
        } catch (\Exception $e) {
            Log::error('[IOR Stripe] Success redirect error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/ior/payment/stripe/cancel
     */
    public function cancel(Request $request)
    {
        $orderId    = $request->query('order_id');
        $frontendUrl = env('FRONTEND_URL', '/');
        return redirect()->away($frontendUrl . "?ior_payment=cancelled&order_id={$orderId}");
    }
}
