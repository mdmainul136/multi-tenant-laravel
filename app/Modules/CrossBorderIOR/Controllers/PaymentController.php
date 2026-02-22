<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorPaymentTransaction;
use App\Modules\CrossBorderIOR\Services\BkashPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // ════════════════════════════════════════════════════
    // BKASH
    // ════════════════════════════════════════════════════

    /**
     * POST /ior/payment/bkash/initiate
     */
    public function bkashInitiate(Request $request, BkashPaymentService $bkash): JsonResponse
    {
        $request->validate([
            'order_id'    => 'required|integer|exists:ior_foreign_orders,id',
            'payment_type'=> 'in:advance,remaining,full',
        ]);

        $order = IorForeignOrder::findOrFail($request->integer('order_id'));
        $paymentType = $request->input('payment_type', 'advance');
        $amount = $paymentType === 'remaining'
            ? $order->remaining_amount
            : $order->advance_amount;

        if (!$amount || $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid payment amount'], 422);
        }

        try {
            if (!$bkash->isConfigured()) {
                return response()->json(['success' => false, 'message' => 'bKash is not configured. Set credentials in IOR Settings.'], 503);
            }

            $callbackUrl = url('/api/ior/payment/bkash/callback') . '?order_id=' . $order->id . '&type=' . $paymentType;
            $result = $bkash->createPayment($order, $amount, $callbackUrl, $paymentType);

            return response()->json([
                'success'      => true,
                'redirect_url' => $result['redirect_url'],
                'payment_id'   => $result['payment_id'],
                'amount'       => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('[IOR bKash] Initiate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /ior/payment/bkash/callback
     * Called by bKash after customer completes payment.
     * This route is NOT authenticated — it's a browser redirect.
     */
    public function bkashCallback(Request $request, BkashPaymentService $bkash)
    {
        $paymentId = $request->query('paymentID');
        $status    = $request->query('status');

        if (!$paymentId || $status !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Payment was cancelled or failed.',
                'status'  => $status,
            ], 400);
        }

        try {
            $result = $bkash->executePayment($paymentId);
            $success = ($result['statusCode'] ?? '') === '0000';

            return response()->json([
                'success'     => $success,
                'transaction' => $result['trxID'] ?? null,
                'message'     => $success ? 'Payment successful!' : 'Payment execution failed.',
                'data'        => $result,
            ], $success ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('[IOR bKash] Callback error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════════════════
    // SSLCOMMERZ
    // ════════════════════════════════════════════════════

    /**
     * POST /ior/payment/sslcommerz/initiate
     */
    public function sslcommerzInitiate(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'     => 'required|integer|exists:ior_foreign_orders,id',
            'payment_type' => 'in:advance,remaining,full',
        ]);

        $order = IorForeignOrder::findOrFail($request->integer('order_id'));
        $paymentType = $request->input('payment_type', 'advance');
        $amount = $paymentType === 'remaining' ? $order->remaining_amount : $order->advance_amount;

        if (!$amount || $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid payment amount'], 422);
        }

        $storeId   = \App\Models\CrossBorderIOR\IorSetting::get('sslcommerz_store_id', '');
        $storePass = \App\Models\CrossBorderIOR\IorSetting::get('sslcommerz_store_pass', '');
        $sandbox   = (bool) \App\Models\CrossBorderIOR\IorSetting::get('sslcommerz_sandbox', true);

        if (empty($storeId) || empty($storePass)) {
            return response()->json(['success' => false, 'message' => 'SSLCommerz not configured.'], 503);
        }

        $apiUrl = $sandbox
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

        $params = [
            'store_id'       => $storeId,
            'store_passwd'   => $storePass,
            'total_amount'   => number_format($amount, 2, '.', ''),
            'currency'       => 'BDT',
            'tran_id'        => $order->order_number . '-' . $paymentType . '-' . time(),
            'success_url'    => url('/api/ior/payment/sslcommerz/success'),
            'fail_url'       => url('/api/ior/payment/sslcommerz/fail'),
            'cancel_url'     => url('/api/ior/payment/sslcommerz/cancel'),
            'ipn_url'        => url('/api/ior/payment/sslcommerz/ipn'),
            'cus_name'       => $order->shipping_full_name ?? 'Customer',
            'cus_phone'      => $order->shipping_phone ?? '',
            'cus_add1'       => $order->shipping_address ?? '',
            'cus_city'       => $order->shipping_city ?? 'Dhaka',
            'cus_country'    => 'Bangladesh',
            'shipping_method'=> 'NO',
            'product_name'   => substr($order->product_name, 0, 200),
            'product_category'=> 'General',
            'product_profile'=> 'general',
            'value_a'        => (string) $order->id,
            'value_b'        => $paymentType,
        ];

        $response = \Illuminate\Support\Facades\Http::asForm()->post($apiUrl, $params);

        if ($response->failed()) {
            return response()->json(['success' => false, 'message' => 'SSLCommerz API error.'], 500);
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'SUCCESS') {
            return response()->json(['success' => false, 'message' => $data['failedreason'] ?? 'SSLCommerz failed.'], 422);
        }

        return response()->json([
            'success'      => true,
            'redirect_url' => $data['GatewayPageURL'],
            'session_key'  => $data['sessionkey'],
            'amount'       => $amount,
        ]);
    }

    /**
     * POST /ior/payment/sslcommerz/ipn
     * SSLCommerz IPN (Instant Payment Notification)
     */
    public function sslcommerzIpn(Request $request): JsonResponse
    {
        // Basic validation
        $valId      = $request->input('val_id');
        $orderId    = $request->input('value_a');
        $payType    = $request->input('value_b', 'advance');
        $amount     = (float) $request->input('amount');
        $status     = $request->input('status');

        if ($status !== 'VALID' || empty($valId)) {
            return response()->json(['status' => 'ignored']);
        }

        // Verify with SSLCommerz API
        $storeId   = \App\Models\CrossBorderIOR\IorSetting::get('sslcommerz_store_id');
        $storePass = \App\Models\CrossBorderIOR\IorSetting::get('sslcommerz_store_pass');
        $sandbox   = (bool) \App\Models\CrossBorderIOR\IorSetting::get('sslcommerz_sandbox', true);

        $verifyUrl = $sandbox
            ? "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php"
            : "https://securepay.sslcommerz.com/validator/api/validationserverAPI.php";

        $verify = \Illuminate\Support\Facades\Http::get($verifyUrl, [
            'val_id'       => $valId,
            'store_id'     => $storeId,
            'store_passwd' => $storePass,
            'format'       => 'json',
        ]);

        $vData = $verify->json();

        if (($vData['status'] ?? '') !== 'VALID') {
            return response()->json(['status' => 'invalid']);
        }

        // Record transaction and update order
        $order = IorForeignOrder::find($orderId);
        if (!$order) {
            return response()->json(['status' => 'order_not_found']);
        }

        IorPaymentTransaction::create([
            'order_id'           => $order->id,
            'transaction_id'     => $vData['tran_id'] ?? null,
            'gateway'            => 'sslcommerz',
            'payment_type'       => $payType,
            'amount'             => $amount,
            'currency'           => 'BDT',
            'status'             => 'paid',
            'val_id'             => $valId,
            'bank_transaction_id'=> $vData['bank_tran_id'] ?? null,
            'card_type'          => $vData['card_type'] ?? null,
            'gateway_response'   => $vData,
        ]);

        $orderUpdate = [];
        if ($payType === 'advance') {
            $orderUpdate['advance_paid']   = true;
            $orderUpdate['order_status']   = IorForeignOrder::STATUS_SOURCING;
            $orderUpdate['payment_status'] = $order->remaining_paid ? 'paid' : 'partial';
        } elseif ($payType === 'remaining') {
            $orderUpdate['remaining_paid'] = true;
            $orderUpdate['payment_status'] = 'paid';
        }

        $order->update($orderUpdate);

        return response()->json(['status' => 'success']);
    }

    /**
     * GET /ior/payment/status/{orderId}
     */
    public function status(int $orderId): JsonResponse
    {
        $order = IorForeignOrder::with('transactions')->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'data' => [
                'order_number'   => $order->order_number,
                'payment_status' => $order->payment_status,
                'advance_paid'   => $order->advance_paid,
                'remaining_paid' => $order->remaining_paid,
                'advance_amount' => $order->advance_amount,
                'remaining_amount'=> $order->remaining_amount,
                'total_paid'     => $order->total_paid,
                'transactions'   => $order->transactions,
            ],
        ]);
    }
}



