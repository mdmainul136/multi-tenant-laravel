<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Create Stripe checkout session
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            // Validate Stripe configuration
            $stripeSecret = config('services.stripe.secret');
            if (empty($stripeSecret) || str_contains($stripeSecret, 'xxx') || str_contains($stripeSecret, 'YOUR')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured. Please add your Stripe API keys to the .env file.',
                    'error' => 'Missing or invalid Stripe configuration. Check STRIPE_KEY and STRIPE_SECRET in .env file.'
                ], 500);
            }

            $request->validate([
                'module_key' => 'required|string',
                'subscription_type' => 'required|in:monthly,annual,lifetime',
            ]);

            // Get tenant ID from authenticated token
            $tenantId = $request->input('token_tenant_id');
            
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant ID not found in token',
                    'error' => 'Please select a tenant from the dashboard'
                ], 400);
            }

            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();
            $module = Module::where('module_key', $request->module_key)->firstOrFail();

            // Calculate price based on subscription type
            $price = $this->calculatePrice($module->price, $request->subscription_type);

            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'module_id' => $module->id,
                'amount' => $price,
                'currency' => 'USD',
                'payment_method' => 'stripe',
                'payment_status' => 'pending',
            ]);

            // Set Stripe API key
            \Stripe\Stripe::setApiKey($stripeSecret);

            // Create Stripe checkout session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $module->module_name,
                            'description' => $module->description,
                        ],
                        'unit_amount' => $price * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel',
                'metadata' => [
                    'payment_id' => $payment->id,
                    'tenant_id' => $tenant->id,
                    'module_id' => $module->id,
                    'subscription_type' => $request->subscription_type,
                ],
            ]);

            // Update payment with session ID
            $payment->update([
                'stripe_session_id' => $session->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                    'url' => $session->url,
                    'payment_id' => $payment->id,
                ]
            ]);

        } catch (\Stripe\Exception\AuthenticationException $e) {
            Log::error('Stripe authentication failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid Stripe API keys. Please check your Stripe configuration.',
                'error' => 'Stripe authentication failed. Verify STRIPE_SECRET in .env file.'
            ], 500);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Stripe API error occurred',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            Log::error('Stripe checkout session creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment after Stripe redirect and process all post-payment actions
     * This is called from the frontend success page with the session_id
     * Works without webhooks - directly fetches session from Stripe
     */
    public function verifyPayment(Request $request)
    {
        try {
            $request->validate([
                'session_id' => 'required|string',
            ]);

            $sessionId = $request->input('session_id');
            $tenantId  = $request->input('token_tenant_id');

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Fetch the session from Stripe
            $session = \Stripe\Checkout\Session::retrieve([
                'id'     => $sessionId,
                'expand' => ['payment_intent', 'payment_intent.payment_method'],
            ]);

            if ($session->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed yet',
                    'status'  => $session->payment_status,
                ], 400);
            }

            // Find the payment record
            $payment = Payment::where('stripe_session_id', $sessionId)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found',
                ], 404);
            }

            // Check if already processed (idempotent)
            if ($payment->payment_status === 'completed') {
                $invoice = \App\Models\Invoice::where('payment_id', $payment->id)->first();
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already processed',
                    'data'    => [
                        'payment_id'     => $payment->id,
                        'invoice_number' => $invoice?->invoice_number,
                        'status'         => 'completed',
                    ],
                ]);
            }

            $subscriptionType = $session->metadata->subscription_type ?? 'monthly';

            // 1. Mark payment as completed
            $payment->update([
                'payment_status'             => 'completed',
                'stripe_payment_intent_id'   => $session->payment_intent->id ?? null,
            ]);

            // 2. Activate module subscription
            $this->activateSubscription(
                $payment->tenant_id,
                $payment->module_id,
                $subscriptionType,
                $payment->id,
                $payment->amount
            );

            // 3. Create invoice
            $invoice = $this->createInvoiceForPayment($payment, $subscriptionType);

            // 4. Save payment method from Stripe session
            $paymentMethod = null;
            $stripePaymentMethod = $session->payment_intent->payment_method ?? null;
            if ($stripePaymentMethod) {
                $pmId = is_object($stripePaymentMethod) ? $stripePaymentMethod->id : $stripePaymentMethod;
                $paymentMethod = $this->savePaymentMethod($payment->tenant_id, $pmId);
            }

            Log::info("Payment verified via session: {$sessionId}, Invoice: {$invoice?->invoice_number}");

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and processed successfully',
                'data'    => [
                    'payment_id'     => $payment->id,
                    'invoice_number' => $invoice?->invoice_number,
                    'invoice_id'     => $invoice?->id,
                    'has_payment_method' => $paymentMethod !== null,
                    'status'         => 'completed',
                ],
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API error during payment verification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment with Stripe',
                'error'   => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stripe webhook handler
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe event type: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful checkout session
     */
    protected function handleCheckoutSessionCompleted($session)
    {
        $paymentId = $session->metadata->payment_id ?? null;
        
        if (!$paymentId) {
            Log::error('Payment ID not found in session metadata');
            return;
        }

        $payment = Payment::find($paymentId);
        
        if (!$payment) {
            Log::error("Payment not found: {$paymentId}");
            return;
        }

        // Mark payment as completed
        $payment->markAsCompleted($session->payment_intent);
        $payment->update([
            'stripe_payment_intent_id' => $session->payment_intent,
            'payment_gateway_response' => $session->toArray(),
        ]);

        // Activate module subscription
        $this->activateSubscription(
            $payment->tenant_id,
            $payment->module_id,
            $session->metadata->subscription_type ?? 'monthly',
            $payment->id,
            $payment->amount
        );

        // Create invoice
        $this->createInvoiceForPayment($payment, $session->metadata->subscription_type ?? 'monthly');

        // Save payment method if available
        if ($session->payment_method) {
            $this->savePaymentMethod($payment->tenant_id, $session->payment_method);
        }

        Log::info("Payment completed, module activated, invoice created: Payment ID {$paymentId}");
    }

    /**
     * Create invoice for completed payment
     */
    protected function createInvoiceForPayment($payment, $subscriptionType)
    {
        try {
            $module = Module::find($payment->module_id);
            
            // Generate unique invoice number
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(
                \App\Models\Invoice::where('tenant_id', $payment->tenant_id)->count() + 1,
                4,
                '0',
                STR_PAD_LEFT
            );

            $invoice = \App\Models\Invoice::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'module_id' => $payment->module_id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'due_date' => now(), // Paid immediately
                'subscription_type' => $subscriptionType,
                'subtotal' => $payment->amount,
                'tax' => 0.00,
                'discount' => 0.00,
                'total' => $payment->amount,
                'status' => 'paid',
                'notes' => "Payment for {$module->module_name} - {$subscriptionType} subscription",
                'metadata' => [
                    'item_type' => 'module',
                    'module_name' => $module->module_name,
                    'module_key' => $module->module_key,
                    'plan' => $subscriptionType,
                    'price' => $payment->amount,
                ],
            ]);

            Log::info("Invoice created: {$invoiceNumber} for payment {$payment->id}");
            
            return $invoice;
        } catch (\Exception $e) {
            Log::error("Failed to create invoice for payment {$payment->id}: " . $e->getMessage());
        }
    }

    /**
     * Save payment method from Stripe
     */
    protected function savePaymentMethod($tenantId, $paymentMethodId)
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $stripePaymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);

            // Check if payment method already exists by Stripe ID
            $existingById = \App\Models\PaymentMethod::where('stripe_payment_method_id', $paymentMethodId)->first();
            if ($existingById) {
                return $existingById;
            }

            // Better check: Is there already an active card with the same details for this tenant?
            $brand = strtolower($stripePaymentMethod->card->brand ?? '');
            $last4 = $stripePaymentMethod->card->last4 ?? '';
            $expMonth = $stripePaymentMethod->card->exp_month ?? null;
            $expYear = $stripePaymentMethod->card->exp_year ?? null;

            $existingByDetails = \App\Models\PaymentMethod::where('tenant_id', $tenantId)
                ->where('brand', $brand)
                ->where('last4', $last4)
                ->where('exp_month', $expMonth)
                ->where('exp_year', $expYear)
                ->where('is_active', true)
                ->first();

            if ($existingByDetails) {
                // Update the Stripe PM ID to the latest one
                $existingByDetails->update(['stripe_payment_method_id' => $paymentMethodId]);
                Log::info("Payment method updated with new Stripe ID: {$paymentMethodId} for existing card {$brand} {$last4}");
                return $existingByDetails;
            }

            // Create new payment method record if no match found
            $paymentMethod = \App\Models\PaymentMethod::create([
                'tenant_id' => $tenantId,
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
            $count = \App\Models\PaymentMethod::where('tenant_id', $tenantId)->count();
            if ($count === 1) {
                $paymentMethod->setAsDefault();
            }

            Log::info("Payment method saved: {$paymentMethodId} for tenant {$tenantId}");
            
            return $paymentMethod;
        } catch (\Exception $e) {
            Log::error("Failed to save payment method {$paymentMethodId}: " . $e->getMessage());
        }
    }

    /**
     * Handle payment intent succeeded
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if ($payment && !$payment->isCompleted()) {
            $payment->markAsCompleted($paymentIntent->id);
        }
    }

    /**
     * Handle payment intent failed
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if ($payment) {
            $payment->markAsFailed();
            Log::error("Payment failed: " . $paymentIntent->last_payment_error->message ?? 'Unknown error');
        }
    }

    /**
     * Activate subscription after successful payment
     */
    protected function activateSubscription($tenantId, $moduleId, $subscriptionType, $paymentId, $pricePaid)
    {
        $tenant = Tenant::find($tenantId);
        $module = Module::find($moduleId);

        if (!$tenant || !$module) {
            Log::error("Tenant or Module not found");
            return;
        }

        // Calculate expiry date based on subscription type
        $expiresAt = $this->calculateExpiryDate($subscriptionType);

        // Subscribe to module
        $result = $this->moduleService->subscribeModule(
            $tenant->id,
            $module->module_key,
            [
                'status' => 'active',
                'subscription_type' => $subscriptionType,
                'price_paid' => $pricePaid,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'payment_id' => $paymentId,
            ]
        );

        if ($result['success']) {
            Log::info("Module {$module->module_key} activated for tenant {$tenant->tenant_id}");
        } else {
            Log::error("Failed to activate module: " . $result['message']);
        }
    }

    /**
     * Calculate price based on subscription type
     */
    protected function calculatePrice($basePrice, $subscriptionType)
    {
        switch ($subscriptionType) {
            case 'monthly':
                return $basePrice;
            case 'annual':
                return $basePrice * 12 * 0.83; // 17% discount
            case 'lifetime':
                return $basePrice * 24; // 2 years worth
            default:
                return $basePrice;
        }
    }

    /**
     * Calculate expiry date based on subscription type
     */
    protected function calculateExpiryDate($subscriptionType)
    {
        switch ($subscriptionType) {
            case 'monthly':
                return now()->addMonth();
            case 'annual':
                return now()->addYear();
            case 'lifetime':
                return null; // No expiry
            default:
                return now()->addMonth();
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus($paymentId)
    {
        $payment = Payment::with(['tenant', 'module'])->findOrFail($paymentId);

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }
}
