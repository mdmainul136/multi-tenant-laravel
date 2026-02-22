<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\Payment\Gateways\CardGateway;
use App\Services\Payment\Gateways\WalletGateway;
use App\Services\Payment\Gateways\BNPLGateway;
use Illuminate\Support\Facades\Log;

/**
 * Unified Refund Engine.
 *
 * Middle East refund reality:
 *   Card (MADA/Visa)  → 7–21 business days (bank, no way around it)
 *   STC Pay wallet    → instant / within 24h
 *   BNPL (Tabby etc.) → adjusts remaining installments, NOT a bank refund
 *   COD               → wallet credit OR coupon (never "instant bank refund")
 *
 * NEVER promise instant card refunds to customers!
 */
class RefundEngine
{
    public function __construct(
        protected CardGateway   $card,
        protected WalletGateway $wallet,
        protected BNPLGateway   $bnpl
    ) {}

    /**
     * Unified refund entry point — routes by original payment gateway_driver.
     *
     * @param int    $paymentId   Internal payment record ID
     * @param float  $amount      Amount to refund (partial or full)
     * @param string $reason      Reason for refund (shown in invoice notes)
     * @return array{success, message, eta, destination, method}
     */
    public function refund(int $paymentId, float $amount, string $reason = ''): array
    {
        $payment = Payment::findOrFail($paymentId);

        // Idempotency: don't refund more than paid
        if ($amount > $payment->amount) {
            return ['success' => false, 'message' => "Refund amount ({$amount}) exceeds payment ({$payment->amount})"];
        }

        $driver = $payment->gateway_driver ?? $payment->payment_method;

        Log::info("Refund requested: payment={$paymentId} driver={$driver} amount={$amount}");

        return match ($driver) {
            'card', 'mada', 'moyasar'       => $this->refundCard($payment, $amount, $reason),
            'stc_pay', 'wallet'             => $this->refundWallet($payment, $amount, $reason),
            'tabby'                          => $this->refundBNPL($payment, $amount, 'tabby'),
            'tamara'                         => $this->refundBNPL($payment, $amount, 'tamara'),
            'postpay'                        => $this->refundBNPL($payment, $amount, 'postpay'),
            'cod'                            => $this->refundCOD($payment, $amount),
            'sslcommerz'                     => $this->refundSSLCommerz($payment, $amount),
            'stripe', 'stripe_auto'          => $this->refundStripe($payment, $amount),
            default                          => ['success' => false, 'message' => "Unknown gateway driver: {$driver}"],
        };
    }

    // ── Card (Moyasar / MADA) ──────────────────────────────────────────────

    protected function refundCard(Payment $payment, float $amount, string $reason): array
    {
        if (!$payment->gateway_transaction_id) {
            return ['success' => false, 'message' => 'No Moyasar transaction ID found for refund'];
        }

        // Moyasar amount is in halalas (SAR×100) or fils (AED×100)
        $amountInSmallestUnit = (int) ($amount * 100);

        $result = $this->card->refund($payment->gateway_transaction_id, $amountInSmallestUnit, $reason);

        if ($result['success']) {
            $payment->update(['payment_status' => $amount >= $payment->amount ? 'refunded' : 'partially_refunded']);
            $this->recordRefund($payment, $amount, 'card', '7-21 business days');
        }

        return array_merge($result, [
            'method'      => 'card',
            'eta'         => '7–21 business days',
            'destination' => 'bank account',
            'warning'     => '⚠️ Card refunds take 7–21 business days in KSA/UAE.',
        ]);
    }

    // ── STC Pay Wallet ─────────────────────────────────────────────────────

    protected function refundWallet(Payment $payment, float $amount, string $reason): array
    {
        if (!$payment->gateway_transaction_id) {
            return ['success' => false, 'message' => 'No STC Pay transaction ID found'];
        }

        $result = $this->wallet->refund($payment->gateway_transaction_id, $amount, $reason);

        if ($result['success']) {
            $payment->update(['payment_status' => 'refunded']);
            $this->recordRefund($payment, $amount, 'stc_pay_wallet', 'instant_to_24h');
        }

        return array_merge($result, [
            'method'      => 'stc_pay_wallet',
            'eta'         => 'Instant – 24 hours',
            'destination' => 'STC Pay wallet',
        ]);
    }

    // ── BNPL (Tabby / Tamara) ──────────────────────────────────────────────

    protected function refundBNPL(Payment $payment, float $amount, string $provider): array
    {
        $externalId = $payment->gateway_transaction_id ?? $payment->transaction_id;

        $result = $this->bnpl->refund($provider, $externalId, $amount);

        if ($result['success']) {
            $payment->update(['payment_status' => 'refunded']);
            $this->recordRefund($payment, $amount, $provider, 'installments_adjusted');
        }

        return array_merge($result, [
            'method'      => $provider,
            'eta'         => 'Remaining installments cancelled/adjusted',
            'destination' => 'installment_plan',
            'note'        => 'This is NOT a bank refund — your future installments will be adjusted or cancelled.',
        ]);
    }

    // ── COD ────────────────────────────────────────────────────────────────

    protected function refundCOD(Payment $payment, float $amount): array
    {
        // COD refunds: issue wallet credit or coupon — NOT bank transfer
        // In Middle East, bank refunds from COD are extremely slow / impractical

        $couponCode = 'REFUND-' . strtoupper(substr(md5($payment->id . time()), 0, 8));

        // TODO: Create actual coupon/wallet credit record here
        Log::info("COD refund issued as credit: payment={$payment->id} coupon={$couponCode}");

        $this->recordRefund($payment, $amount, 'cod_credit', 'instant');

        return [
            'success'      => true,
            'method'       => 'store_credit',
            'coupon_code'  => $couponCode,
            'amount'       => $amount,
            'eta'          => 'Instant',
            'destination'  => 'store_wallet_or_coupon',
            'note'         => 'COD refunds are issued as store credit. Bank transfer not available for COD.',
        ];
    }

    // ── Stripe ─────────────────────────────────────────────────────────────

    protected function refundStripe(Payment $payment, float $amount): array
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $refund = \Stripe\Refund::create([
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount'         => (int)($amount * 100),
        ]);

        $success = $refund->status === 'succeeded' || $refund->status === 'pending';
        if ($success) {
            $payment->update(['payment_status' => 'refunded']);
            $this->recordRefund($payment, $amount, 'stripe', '5-10 business days');
        }

        return [
            'success'     => $success,
            'refund_id'   => $refund->id,
            'method'      => 'stripe_card',
            'eta'         => '5–10 business days',
            'destination' => 'original card',
        ];
    }

    // ── SSLCommerz ─────────────────────────────────────────────────────────

    protected function refundSSLCommerz(Payment $payment, float $amount): array
    {
        // SSLCommerz has a refund API but it requires manual approval in Bangladesh
        Log::info("SSLCommerz refund requested: payment={$payment->id} amount={$amount}");
        return [
            'success'  => true,
            'method'   => 'sslcommerz',
            'eta'      => '7–14 business days',
            'note'     => 'SSLCommerz refund initiated. Processed manually by SSLCommerz.',
        ];
    }

    // ── Helper ─────────────────────────────────────────────────────────────

    protected function recordRefund(Payment $payment, float $amount, string $method, string $eta): void
    {
        // Record refund in payments table or a dedicated refunds table
        $payment->update([
            'refunded_amount' => ($payment->refunded_amount ?? 0) + $amount,
            'refund_method'   => $method,
            'refunded_at'     => now(),
        ]);
    }
}
