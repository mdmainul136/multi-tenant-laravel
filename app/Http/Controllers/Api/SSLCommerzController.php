<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\ModuleService;
use App\Services\SSLCommerzService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SSLCommerzController extends Controller
{
    public function __construct(
        protected SSLCommerzService $sslCommerz,
        protected ModuleService     $moduleService
    ) {}

    // ── Initiate Payment ───────────────────────────────────────────────────

    /**
     * POST /api/payment/sslcommerz/initiate
     * Tenant requests a checkout — returns SSLCommerz redirect URL.
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'module_key'        => 'required|string|exists:modules,module_key',
            'subscription_type' => 'required|in:monthly,annual,lifetime',
        ]);

        $tenantId = $request->attributes->get('tenant_id') ?? $request->input('token_tenant_id');

        $tenant = Tenant::where('tenant_id', $tenantId)->first();
        $module = Module::where('module_key', $request->module_key)->firstOrFail();

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Calculate price in BDT (or use 'amount_bdt' from request if provided)
        $usdPrice = $this->calculatePrice($module->price, $request->subscription_type);
        $bdtPrice = $request->amount_bdt ?? ($usdPrice * 110); // rough USD→BDT

        // Create pending payment record
        $payment = Payment::create([
            'tenant_id'      => $tenant->id,
            'module_id'      => $module->id,
            'amount'         => $bdtPrice,
            'currency'       => 'BDT',
            'payment_method' => 'sslcommerz',
            'payment_status' => 'pending',
        ]);

        try {
            $result = $this->sslCommerz->initiatePayment([
                'total_amount'      => $bdtPrice,
                'tran_id'           => 'TRX-' . $payment->id . '-' . time(),
                'product_name'      => $module->module_name . ' — ' . $request->subscription_type,
                'cus_name'          => $tenant->name ?? $tenant->tenant_id,
                'cus_email'         => $tenant->email ?? 'noreply@example.com',
                'cus_phone'         => $tenant->phone ?? '01xxxxxxxxx',
                'value_a'           => $payment->id,                  // payment_id
                'value_b'           => $tenant->id,                   // tenant_id
                'value_c'           => $module->id,                   // module_id
                'value_d'           => $request->subscription_type,   // sub type
            ]);

            // Store session key
            $payment->update(['transaction_id' => $result['sessionkey']]);

            return response()->json([
                'success'      => true,
                'redirect_url' => $result['redirect_url'],
                'session_key'  => $result['sessionkey'],
                'payment_id'   => $payment->id,
            ]);

        } catch (\Exception $e) {
            $payment->update(['payment_status' => 'failed']);
            Log::error('SSLCommerz initiation error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Callbacks (called by SSLCommerz redirect) ──────────────────────────

    /** GET/POST /api/payment/sslcommerz/success */
    public function success(Request $request)
    {
        $valId     = $request->input('val_id');
        $paymentId = $request->input('value_a');

        try {
            $validation = $this->sslCommerz->validatePayment($valId);

            if (!$validation['success']) {
                return $this->redirectFrontend('fail', ['reason' => 'Validation failed']);
            }

            $this->processSuccessfulPayment($paymentId, $validation['data']);

            return $this->redirectFrontend('success', ['payment_id' => $paymentId]);

        } catch (\Exception $e) {
            Log::error('SSLCommerz success callback error: ' . $e->getMessage());
            return $this->redirectFrontend('fail', ['reason' => $e->getMessage()]);
        }
    }

    /** POST /api/payment/sslcommerz/fail */
    public function fail(Request $request)
    {
        $paymentId = $request->input('value_a');
        if ($paymentId) Payment::find($paymentId)?->markAsFailed();
        return $this->redirectFrontend('cancel', ['reason' => 'Payment failed']);
    }

    /** POST /api/payment/sslcommerz/cancel */
    public function cancel(Request $request)
    {
        $paymentId = $request->input('value_a');
        if ($paymentId) Payment::find($paymentId)?->update(['payment_status' => 'cancelled']);
        return $this->redirectFrontend('cancel');
    }

    /**
     * POST /api/payment/sslcommerz/ipn
     * Instant Payment Notification — server-to-server (most reliable).
     */
    public function ipn(Request $request)
    {
        // Verify IPN hash
        if (!$this->sslCommerz->verifyIpnHash($request->all())) {
            Log::warning('SSLCommerz IPN hash verification failed', $request->all());
            return response()->json(['status' => 'invalid hash'], 400);
        }

        $status    = $request->input('status');
        $paymentId = $request->input('value_a');

        if ($status === 'VALID' || $status === 'VALIDATED') {
            $this->processSuccessfulPayment($paymentId, $request->all());
            Log::info("SSLCommerz IPN processed: payment_id={$paymentId}");
        } elseif ($status === 'FAILED') {
            Payment::find($paymentId)?->markAsFailed();
        }

        return response()->json(['status' => 'received']);
    }

    // ── Also: verify by frontend (same as Stripe verifyPayment) ───────────

    /**
     * POST /api/payment/sslcommerz/verify
     */
    public function verify(Request $request)
    {
        $request->validate(['val_id' => 'required|string', 'payment_id' => 'required|integer']);

        $validation = $this->sslCommerz->validatePayment($request->val_id);

        if (!$validation['success']) {
            return response()->json(['success' => false, 'message' => 'Payment not valid'], 400);
        }

        $this->processSuccessfulPayment($request->payment_id, $validation['data']);

        return response()->json(['success' => true, 'message' => 'Payment verified']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    protected function processSuccessfulPayment(int|string $paymentId, array $gatewayData): void
    {
        $payment = Payment::find($paymentId);

        if (!$payment || $payment->payment_status === 'completed') return;

        $subscriptionType = $gatewayData['value_d'] ?? 'monthly';
        $tenantId         = $payment->tenant_id;
        $moduleId         = $payment->module_id;

        // Mark payment complete
        $payment->update([
            'payment_status' => 'completed',
            'paid_at'        => now(),
        ]);

        // Activate module subscription
        $tenant = Tenant::find($tenantId);
        $module = Module::find($moduleId);

        if ($tenant && $module) {
            $expiresAt = match($subscriptionType) {
                'annual'   => now()->addYear(),
                'lifetime' => null,
                default    => now()->addMonth(),
            };

            $this->moduleService->subscribeModule($tenant->tenant_id, $module->module_key, [
                'subscription_type' => $subscriptionType,
                'expires_at'        => $expiresAt,
                'payment_id'        => $payment->id,
                'price_paid'        => $payment->amount,
            ]);
        }

        Log::info("SSLCommerz payment processed: payment_id={$paymentId}");
    }

    protected function calculatePrice(float $basePrice, string $type): float
    {
        return match ($type) {
            'annual'   => $basePrice * 12 * 0.83,
            'lifetime' => $basePrice * 24,
            default    => $basePrice,
        };
    }

    protected function redirectFrontend(string $status, array $params = [])
    {
        $url     = config('app.frontend_url', 'http://localhost:3000');
        $qs      = http_build_query(array_merge(['status' => $status], $params));
        return redirect("{$url}/payment/result?{$qs}");
    }
}
