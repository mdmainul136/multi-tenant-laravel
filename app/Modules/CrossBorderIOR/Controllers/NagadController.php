<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Modules\CrossBorderIOR\Services\NagadPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * NagadController
 *
 * Handles Nagad MFS payment initiation and callback verification.
 *
 * Routes:
 *   POST /ior/payment/nagad/initiate   → initiate()
 *   GET  /ior/payment/nagad/callback   → callback()   (public — browser redirect)
 */
class NagadController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // POST /ior/payment/nagad/initiate
    // ──────────────────────────────────────────────────────────────

    public function initiate(Request $request, NagadPaymentService $nagad): JsonResponse
    {
        $data = $request->validate([
            'order_id'     => 'required|integer|exists:ior_foreign_orders,id',
            'payment_type' => 'in:advance,remaining,full',
        ]);

        $order       = IorForeignOrder::findOrFail($data['order_id']);
        $paymentType = $data['payment_type'] ?? 'advance';

        $amount = match ($paymentType) {
            'remaining' => (float) $order->remaining_amount,
            'full'      => (float) ($order->final_price_bdt ?? $order->estimated_price_bdt),
            default     => (float) $order->advance_amount,
        };

        if (!$amount || $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid payment amount.'], 422);
        }

        if (!$nagad->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Nagad is not configured. Set nagad_merchant_id and nagad_merchant_key in IOR Settings.',
            ], 503);
        }

        try {
            $result = $nagad->initiate($order, $amount, $paymentType);

            return response()->json([
                'success'        => true,
                'redirect_url'   => $result['redirect_url'],
                'payment_ref_id' => $result['payment_ref_id'],
                'transaction_id' => $result['transaction_id'],
                'amount'         => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('[IOR Nagad] Initiate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /ior/payment/nagad/callback
    // Called by Nagad after customer completes payment (browser redirect).
    // This route is PUBLIC (no auth middleware).
    // ──────────────────────────────────────────────────────────────

    public function callback(Request $request, NagadPaymentService $nagad)
    {
        $paymentRefId = $request->query('payment_ref_id')
            ?? $request->query('paymentRefId');
        $orderId      = $request->query('order_id');
        $paymentType  = $request->query('type', 'advance');
        $status       = $request->query('status', '');

        Log::info('[IOR Nagad] Callback received', $request->all());

        $frontendUrl = env('FRONTEND_URL', '/');

        // If Nagad says payment failed
        if (strtolower($status) === 'cancel' || strtolower($status) === 'failed') {
            return redirect()->away($frontendUrl . '?ior_payment=failed&gateway=nagad');
        }

        if (!$paymentRefId || !$orderId) {
            return redirect()->away($frontendUrl . '?ior_payment=invalid&gateway=nagad');
        }

        $order = IorForeignOrder::find($orderId);

        if (!$order) {
            return redirect()->away($frontendUrl . '?ior_payment=order_not_found');
        }

        try {
            $result = $nagad->verify($paymentRefId, $order, $paymentType);

            if ($result['success']) {
                return redirect()->away($frontendUrl . '?ior_payment=success&gateway=nagad&order=' . $order->order_number);
            }

            return redirect()->away($frontendUrl . '?ior_payment=failed&gateway=nagad&reason=' . urlencode($result['message'] ?? ''));
        } catch (\Exception $e) {
            Log::error('[IOR Nagad] Callback error: ' . $e->getMessage());
            return redirect()->away($frontendUrl . '?ior_payment=error&gateway=nagad');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /ior/payment/nagad/status/{orderId}
    // ──────────────────────────────────────────────────────────────

    public function status(int $orderId): JsonResponse
    {
        $order = IorForeignOrder::with('transactions')->findOrFail($orderId);

        $nagadTxn = $order->transactions
            ->where('gateway', 'nagad')
            ->sortByDesc('created_at')
            ->first();

        return response()->json([
            'success'        => true,
            'payment_status' => $order->payment_status,
            'advance_paid'   => $order->advance_paid,
            'remaining_paid' => $order->remaining_paid,
            'transaction'    => $nagadTxn,
        ]);
    }
}
