<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\CODOrder;
use App\Services\Payment\PaymentService;
use App\Services\Payment\CODService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middle East Payment Controller — country-aware endpoint handler.
 */
class MiddleEastPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected CODService     $codService
    ) {}

    // ── 1. Resolve Payment Methods ─────────────────────────────────────────

    /**
     * POST /api/payment/resolve-methods
     * Returns priority-ordered payment methods for the user's country/device/amount.
     *
     * Body: { country, device, amount, currency }
     * Returns: [{ key, label, label_ar, icon, available }, ...]
     */
    public function resolveMethods(Request $request)
    {
        $validated = $request->validate([
            'country'  => 'required|string|size:2',
            'device'   => 'required|in:ios,android,desktop,mobile',
            'amount'   => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        $userId  = $request->attributes->get('user_id');
        $methods = $this->paymentService->resolvePaymentMethods(
            $validated['country'],
            $validated['device'],
            $validated['amount'],
            $userId
        );

        return response()->json([
            'success' => true,
            'country' => strtoupper($validated['country']),
            'methods' => $methods,
        ]);
    }

    // ── 2. Charge ──────────────────────────────────────────────────────────

    /**
     * POST /api/payment/charge
     * Unified charge endpoint — delegates to correct gateway by 'driver'.
     *
     * Body: { driver, amount, currency, ...driver-specific fields }
     */
    public function charge(Request $request)
    {
        $validated = $request->validate([
            'driver'   => 'required|in:mada,card,apple_pay,google_pay,stc_pay,tabby,tamara,postpay,cod',
            'amount'   => 'required|numeric|min:1',
            'currency' => 'required|in:SAR,AED,BDT,USD',
        ]);

        $tenantId = $request->attributes->get('tenant_id') ?? $request->input('token_tenant_id');
        $userId   = $request->attributes->get('user_id');

        $params = array_merge($request->all(), [
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
        ]);

        $result = $this->paymentService->charge($validated['driver'], $params);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // ── 3. Refund ──────────────────────────────────────────────────────────

    /**
     * POST /api/payment/refund
     * Body: { payment_id, amount, reason }
     */
    public function refund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
            'amount'     => 'required|numeric|min:0.01',
            'reason'     => 'nullable|string|max:500',
        ]);

        $result = $this->paymentService->refund(
            $validated['payment_id'],
            $validated['amount'],
            $validated['reason'] ?? ''
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // ── 4. BNPL Eligibility ────────────────────────────────────────────────

    /**
     * POST /api/payment/bnpl/check
     * Body: { provider, amount, currency, customer: { name, email, phone } }
     */
    public function checkBNPL(Request $request)
    {
        $validated = $request->validate([
            'provider'         => 'required|in:tabby,tamara,postpay',
            'amount'           => 'required|numeric|min:50',
            'currency'         => 'required|in:SAR,AED',
            'customer'         => 'nullable|array',
            'customer.name'    => 'nullable|string',
            'customer.email'   => 'nullable|email',
            'customer.phone'   => 'nullable|string',
        ]);

        $result = $this->paymentService->checkBNPLEligibility(
            $validated['provider'],
            $validated['amount'],
            $validated['currency'],
            $validated['customer'] ?? []
        );

        return response()->json($result);
    }

    // ── 5. COD Operations ──────────────────────────────────────────────────

    /**
     * POST /api/payment/cod/create
     * Body: { amount, currency, address, notes }
     */
    public function createCOD(Request $request)
    {
        $validated = $request->validate([
            'amount'   => 'required|numeric|min:1',
            'currency' => 'required|in:SAR,AED',
            'address'  => 'required|string',
            'notes'    => 'nullable|string',
        ]);

        $tenantId = $request->attributes->get('tenant_id') ?? $request->input('token_tenant_id');
        $userId   = $request->attributes->get('user_id') ?? 1;

        // Check availability (amount limit + risk)
        $avail = $this->codService->isAvailable($userId, $validated['amount'], $validated['currency']);

        if (!$avail['available']) {
            return response()->json(['success' => false, 'message' => $avail['reason']], 422);
        }

        $order = $this->codService->createOrder(array_merge($validated, [
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
        ]));

        return response()->json([
            'success'      => true,
            'order_id'     => $order->id,
            'status'       => 'pending_payment',
            'requires_otp' => $order->otp_required,
            'risk_level'   => $avail['risk_level'],
        ], 201);
    }

    /**
     * POST /api/payment/cod/{orderId}/verify-otp
     * Body: { otp }
     */
    public function verifyCODOtp(Request $request, int $orderId)
    {
        $order = CODOrder::findOrFail($orderId);
        $valid = $this->codService->verifyOTP($order, $request->input('otp', ''));

        return response()->json([
            'success'   => $valid,
            'message'   => $valid ? 'OTP verified — shipment can proceed' : 'Invalid or expired OTP',
            'shipment_ok' => $valid,
        ], $valid ? 200 : 422);
    }

    /**
     * POST /api/payment/cod/{orderId}/confirm-delivery
     * Body: { delivery_agent_id }
     */
    public function confirmCODDelivery(Request $request, int $orderId)
    {
        $order = CODOrder::findOrFail($orderId);
        $this->codService->confirmDelivery($order, $request->input('delivery_agent_id', 'AGENT'));

        return response()->json(['success' => true, 'status' => 'payment_collected']);
    }

    // ── 6. VAT Calculator ──────────────────────────────────────────────────

    /**
     * POST /api/payment/vat/calculate
     * Body: { amount, country }
     * Returns: { subtotal, vat_rate, vat_percent, vat_amount, total, currency }
     */
    public function calculateVAT(Request $request)
    {
        $validated = $request->validate([
            'amount'  => 'required|numeric|min:0',
            'country' => 'required|string|size:2',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->paymentService->calculateVAT($validated['amount'], $validated['country']),
        ]);
    }

    // ── 7. Split Payment Calculator ────────────────────────────────────────

    /**
     * POST /api/payment/split-calculate
     * Body: { amount, platform_rate, country }
     */
    public function calculateSplit(Request $request)
    {
        $validated = $request->validate([
            'amount'        => 'required|numeric|min:0',
            'platform_rate' => 'required|numeric|between:0,1',
            'country'       => 'required|string|size:2',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->paymentService->calculateSplit(
                $validated['amount'],
                $validated['platform_rate'],
                $validated['country']
            ),
        ]);
    }

    // ── 8. Invoice Download ────────────────────────────────────────────────

    /**
     * GET /api/invoices/{id}/download
     * Returns ZATCA/UAE-compliant invoice as JSON (PDF to be rendered by frontend or dompdf).
     */
    public function downloadInvoice(Request $request, int $id)
    {
        $invoice = Invoice::findOrFail($id);
        $country = $request->query('country', 'SA');

        $data = $this->paymentService->generateInvoice($invoice, $country);

        return response()->json(['success' => true, 'invoice' => $data]);
    }
}
