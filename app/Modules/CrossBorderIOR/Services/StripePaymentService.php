<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CrossBorderIOR\IorForeignOrder;
use App\Models\CrossBorderIOR\IorPaymentTransaction;
use App\Models\CrossBorderIOR\IorSetting;

/**
 * StripePaymentService
 * Uses Stripe Checkout Sessions (hosted page) — no frontend card handling needed.
 * Webhook verifies payment and updates order.
 *
 * Credentials: STRIPE_SECRET_KEY in .env (global), or overridable via ior_settings.
 * Public key: STRIPE_PUBLISHABLE_KEY in .env for frontend.
 */
class StripePaymentService
{
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        // Prefer IOR-specific override; fall back to .env
        $this->secretKey     = IorSetting::get('stripe_secret_key', config('services.stripe.secret', ''));
        $this->webhookSecret = IorSetting::get('stripe_webhook_secret', config('services.stripe.webhook_secret', ''));
    }

    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    /**
     * Create a Stripe Checkout Session.
     *
     * @param IorForeignOrder $order
     * @param float           $amount      — BDT amount
     * @param string          $paymentType — advance | remaining | full
     * @return array          ['url' => <checkout URL>, 'session_id' => ...]
     */
    public function createCheckoutSession(
        IorForeignOrder $order,
        float $amount,
        string $paymentType = 'advance'
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe secret key not configured. Set STRIPE_SECRET_KEY in .env or IOR Settings.');
        }

        $amountPaisa = (int) round($amount * 100); // Stripe uses smallest currency unit

        // Stripe doesn't officially support BDT — use BDT if supported, else USD conversion
        // BDT IS supported by Stripe as of 2024
        $currency = 'bdt';

        $productName = mb_substr($order->product_name, 0, 200);
        $description = "IOR Order #{$order->order_number} — {$paymentType} payment";

        $successUrl = url('/api/ior/payment/stripe/success') . '?session_id={CHECKOUT_SESSION_ID}&order_id=' . $order->id . '&type=' . $paymentType;
        $cancelUrl  = url('/api/ior/payment/stripe/cancel')  . '?order_id=' . $order->id;

        $payload = [
            'mode'                       => 'payment',
            'currency'                   => $currency,
            'success_url'                => $successUrl,
            'cancel_url'                 => $cancelUrl,
            'client_reference_id'        => $order->id . '|' . $paymentType,
            'metadata[order_id]'         => $order->id,
            'metadata[order_number]'     => $order->order_number,
            'metadata[payment_type]'     => $paymentType,
            'line_items[0][quantity]'    => 1,
            'line_items[0][price_data][currency]'               => $currency,
            'line_items[0][price_data][unit_amount]'            => $amountPaisa,
            'line_items[0][price_data][product_data][name]'     => $productName,
            'line_items[0][price_data][product_data][description]' => $description,
        ];

        if ($order->product_image_url) {
            $payload['line_items[0][price_data][product_data][images][0]'] = $order->product_image_url;
        }

        if ($order->shipping_full_name) {
            $payload['customer_email'] = $order->guest_email ?? $order->user?->email ?? null;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->timeout(20)
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if ($response->failed()) {
            $err = $response->json('error.message', $response->body());
            throw new \RuntimeException('Stripe Checkout Session failed: ' . $err);
        }

        $session = $response->json();

        // Record initiated transaction
        IorPaymentTransaction::create([
            'order_id'              => $order->id,
            'gateway'               => 'stripe',
            'payment_type'          => $paymentType,
            'amount'                => $amount,
            'currency'              => 'BDT',
            'status'                => 'initiated',
            'stripe_session_id'     => $session['id'],
            'gateway_response'      => $session,
        ]);

        return [
            'session_id'  => $session['id'],
            'checkout_url'=> $session['url'],
            'amount'      => $amount,
            'currency'    => 'BDT',
        ];
    }

    /**
     * Verify Stripe webhook signature and handle payment completion.
     * Call from POST /api/ior/payment/stripe/webhook
     */
    public function handleWebhook(string $payload, string $sigHeader): array
    {
        if (empty($this->webhookSecret)) {
            // Permissive mode (dev) — parse without verification
            $event = json_decode($payload, true);
        } else {
            $event = $this->verifyWebhookSignature($payload, $sigHeader);
        }

        $type = $event['type'] ?? '';

        Log::info("[IOR Stripe] Webhook event: $type");

        if ($type === 'checkout.session.completed') {
            return $this->handleSessionCompleted($event['data']['object'] ?? []);
        }

        if ($type === 'payment_intent.payment_failed') {
            $pi = $event['data']['object'] ?? [];
            Log::warning('[IOR Stripe] Payment failed for PI: ' . ($pi['id'] ?? ''));
            return ['status' => 'payment_failed'];
        }

        return ['status' => 'ignored', 'event' => $type];
    }

    private function handleSessionCompleted(array $session): array
    {
        $sessionId   = $session['id']    ?? null;
        $paymentIntent = $session['payment_intent'] ?? null;
        $metadata    = $session['metadata'] ?? [];
        $orderId     = $metadata['order_id']     ?? null;
        $paymentType = $metadata['payment_type'] ?? 'advance';

        if (!$orderId || !$sessionId) {
            Log::error('[IOR Stripe] Webhook missing order_id or session_id');
            return ['status' => 'error'];
        }

        // Update transaction
        $tx = IorPaymentTransaction::where('stripe_session_id', $sessionId)->first();
        if ($tx) {
            $tx->update([
                'status'               => 'paid',
                'stripe_payment_intent'=> $paymentIntent,
                'transaction_id'       => $paymentIntent,
                'gateway_response'     => $session,
            ]);
        } else {
            // Create if somehow missing (direct webhook without initiate call)
            $amountBdt = $session['amount_total'] / 100;
            IorPaymentTransaction::create([
                'order_id'              => $orderId,
                'gateway'               => 'stripe',
                'payment_type'          => $paymentType,
                'amount'                => $amountBdt,
                'currency'              => 'BDT',
                'status'                => 'paid',
                'stripe_session_id'     => $sessionId,
                'stripe_payment_intent' => $paymentIntent,
                'transaction_id'        => $paymentIntent,
                'gateway_response'      => $session,
            ]);
        }

        // Update order payment status
        $order = IorForeignOrder::find($orderId);
        if ($order) {
            $update = [];
            if ($paymentType === 'advance') {
                $update['advance_paid']   = true;
                $update['payment_status'] = ($order->remaining_paid || $paymentType === 'full') ? 'paid' : 'partial';
                $update['order_status']   = IorForeignOrder::STATUS_SOURCING;
            } elseif ($paymentType === 'remaining' || $paymentType === 'full') {
                $update['remaining_paid'] = true;
                $update['payment_status'] = 'paid';
            }
            $order->update($update);
        }

        return ['status' => 'success', 'order_id' => $orderId, 'payment_type' => $paymentType];
    }

    /**
     * Verify Stripe webhook signature (HMAC SHA-256).
     */
    private function verifyWebhookSignature(string $payload, string $sigHeader): array
    {
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$key, $val] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $val;
        }

        $timestamp = $parts['t'] ?? 0;
        $signature = $parts['v1'] ?? '';

        // Prevent replay attacks — reject events older than 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            throw new \RuntimeException('Stripe webhook timestamp too old (possible replay attack).');
        }

        $signed  = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $this->webhookSecret);

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Stripe webhook signature mismatch.');
        }

        return json_decode($payload, true);
    }

    /**
     * Get a Checkout Session status directly from Stripe API (for success redirect verification).
     */
    public function getSession(string $sessionId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($response->failed()) {
            throw new \RuntimeException('Stripe get session failed: ' . $response->body());
        }

        return $response->json();
    }
}



