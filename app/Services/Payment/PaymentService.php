<?php

namespace App\Services\Payment;

use App\Services\Payment\Gateways\CardGateway;
use App\Services\Payment\Gateways\WalletGateway;
use App\Services\Payment\Gateways\BNPLGateway;
use Illuminate\Support\Facades\Log;

/**
 * Central PaymentService — unified façade for all payment operations.
 *
 * Usage:
 *   $payment = app(PaymentService::class);
 *   $methods = $payment->resolvePaymentMethods('SA', 'ios', 250);
 *   $result  = $payment->charge('mada', [...]);
 *   $refund  = $payment->refund($paymentId, 100);
 */
class PaymentService
{
    public function __construct(
        protected PaymentMethodPriorityEngine $priorityEngine,
        protected CardGateway                 $card,
        protected WalletGateway               $wallet,
        protected BNPLGateway                 $bnpl,
        protected CODService                  $cod,
        protected RefundEngine                $refund,
        protected VATInvoiceService           $vat
    ) {}

    // ── Method Resolution ──────────────────────────────────────────────────

    /**
     * Get priority-ordered payment methods for a given context.
     * Returns labelled list ready for frontend rendering.
     */
    public function resolvePaymentMethods(
        string  $country,
        string  $device,
        float   $amount,
        ?int    $userId = null
    ): array {
        $keys    = $this->priorityEngine->resolve($country, $device, $amount, $userId);
        $methods = [];

        foreach ($keys as $key) {
            $label   = $this->priorityEngine->getMethodLabel($key);
            $methods[] = array_merge($label, [
                'key'       => $key,
                'available' => true,
            ]);
        }

        return $methods;
    }

    // ── Charge ─────────────────────────────────────────────────────────────

    /**
     * Charge the customer using the selected payment method.
     * Each driver has its own flow (see gateway classes for details).
     */
    public function charge(string $driver, array $params): array
    {
        Log::info("PaymentService::charge driver={$driver} amount={$params['amount']}");

        return match ($driver) {

            // ── Card (MADA / Visa / Apple Pay via Moyasar) ─────────────────
            'mada', 'card', 'apple_pay', 'google_pay' => $this->card->charge(
                amount:      (int)(($params['amount'] ?? 0) * 100), // convert to halalas/fils
                currency:    $params['currency'] ?? 'SAR',
                source:      $params['source'],          // ['type' => 'token', 'token' => '...']
                description: $params['description'] ?? '',
                metadata:    $params['metadata'] ?? []
            ),

            // ── STC Pay (2-step OTP) ───────────────────────────────────────
            'stc_pay' => isset($params['otp'])
                ? $this->wallet->confirm($params['payment_reference'], $params['otp'], $params['amount'])
                : $this->wallet->initiate($params['mobile'], $params['amount'], $params['reference'] ?? uniqid(), $params['description'] ?? ''),

            // ── BNPL (Tabby / Tamara / Postpay) ───────────────────────────
            'tabby', 'tamara', 'postpay' => $this->bnpl->initiate($driver, $params),

            // ── COD ────────────────────────────────────────────────────────
            'cod' => (function () use ($params) {
                $order = $this->cod->createOrder($params);
                return ['success' => true, 'order_id' => $order->id, 'status' => 'pending_payment', 'requires_otp' => $order->otp_required];
            })(),

            default => ['success' => false, 'message' => "Unknown payment driver: {$driver}"],
        };
    }

    // ── Refund ─────────────────────────────────────────────────────────────

    /**
     * Refund a payment — routes to correct engine by driver.
     */
    public function refund(int $paymentId, float $amount, string $reason = ''): array
    {
        return $this->refund->refund($paymentId, $amount, $reason);
    }

    // ── BNPL Eligibility ───────────────────────────────────────────────────

    /**
     * Check BNPL eligibility before showing the option.
     */
    public function checkBNPLEligibility(string $provider, float $amount, string $currency, array $customer): array
    {
        return $this->bnpl->checkEligibility($provider, $amount, $currency, $customer);
    }

    // ── VAT Invoice ────────────────────────────────────────────────────────

    /**
     * Generate ZATCA/UAE-compliant VAT invoice data.
     */
    public function generateInvoice(\App\Models\Invoice $invoice, string $country): array
    {
        return $this->vat->generate($invoice, $country);
    }

    /**
     * Calculate VAT for a given country and amount.
     */
    public function calculateVAT(float $amount, string $country): array
    {
        return $this->vat->calculateVAT($amount, $country);
    }

    // ── Split Payment (Marketplace / Multi-vendor) ─────────────────────────

    /**
     * Calculate split payment for marketplace scenario.
     * Saudi/UAE law requires clear invoice splits and VAT per party.
     *
     * @param float $totalAmount  Customer's total payment
     * @param float $platformRate Platform commission rate (e.g. 0.10 = 10%)
     * @param string $country
     */
    public function calculateSplit(float $totalAmount, float $platformRate, string $country): array
    {
        $vat              = $this->vat->calculateVAT($totalAmount, $country);
        $grossAmount      = $vat['subtotal'];
        $platformCommission = round($grossAmount * $platformRate, 2);
        $vendorPayout     = round($grossAmount - $platformCommission, 2);

        return [
            'total_charged'        => $vat['total'],
            'subtotal'             => $vat['subtotal'],
            'vat_amount'           => $vat['vat_amount'],
            'vat_rate'             => $vat['vat_rate'],
            'platform_commission'  => $platformCommission,
            'platform_rate'        => $platformRate,
            'vendor_payout'        => $vendorPayout,
            'currency'             => $vat['currency'],
            'note'                 => 'VAT collected by platform; split excludes VAT per party',
        ];
    }
}
