<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomainOrder;
use App\Models\Tenant;
use App\Services\DomainSearchService;
use App\Services\DomainRegistrationService;
use App\Services\NamecheapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DomainStoreController extends Controller
{
    protected DomainSearchService $domainSearchService;
    protected DomainRegistrationService $registrationService;
    protected \App\Services\NamecheapService $namecheapService;

    public function __construct(
        DomainSearchService $domainSearchService,
        DomainRegistrationService $registrationService,
        \App\Services\NamecheapService $namecheapService
    ) {
        $this->domainSearchService = $domainSearchService;
        $this->registrationService = $registrationService;
        $this->namecheapService = $namecheapService;
    }

    /**
     * Get all domain orders for the current tenant
     */
    public function orders(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $query = DomainOrder::where('tenant_id', $tenantId)
                ->with(['invoice'])
                ->orderBy('created_at', 'desc');

            // 1. Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // 2. Search by domain
            if ($request->has('search')) {
                $query->where('domain', 'LIKE', '%' . $request->search . '%');
            }

            $orders = $query->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch domain orders'
            ], 500);
        }
    }

    /**
     * Get specific domain order details
     */
    public function show(string $id)
    {
        try {
            $order = DomainOrder::with(['invoice', 'payment'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
    }

    /**
     * Search for domain availability
     */
    public function search(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        
        $domain = $request->domain;
        $result = $this->domainSearchService->checkAvailability($domain);
        $suggestions = $this->domainSearchService->getSuggestions(explode('.', $domain)[0]);

        return response()->json([
            'success' => true,
            'data' => [
                'main' => $result,
                'suggestions' => $suggestions
            ]
        ]);
    }

    /**
     * Get WHOIS data
     */
    /**
     * Get WHOIS data
     */
    public function whois(string $domain)
    {
        $data = $this->domainSearchService->getWhoisData($domain);
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Initiate domain purchase (Create Stripe Checkout Session)
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'years' => 'required|integer|min:1|max:10',
            'price' => 'required|numeric'
        ]);

        try {
            $tenantId = $request->attributes->get('tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            // 1. Check if an existing pending/failed order exists for this domain and delete it
            // This avoids unique constraint violations on retries.
            DomainOrder::where('domain', $request->domain)
                ->whereIn('status', ['pending', 'failed'])
                ->delete();

            // 2. Create Domain Order
            $order = DomainOrder::create([
                'tenant_id' => $tenantId,
                'domain' => $request->domain,
                'amount' => $request->price,
                'currency' => 'USD',
                'registration_years' => $request->years ?? 1,
                'status' => 'pending'
            ]);

            // Set Stripe API key
            $stripeSecret = config('services.stripe.secret');
            \Stripe\Stripe::setApiKey($stripeSecret);

            // Use first value only in case FRONTEND_URL has multiple (Stripe needs exactly one URL)
            $baseUrl = explode(',', config('app.frontend_url'))[0];
            $successUrl = rtrim($baseUrl, '/') . '/domains?purchase_success=true';
            $cancelUrl  = rtrim($baseUrl, '/') . '/domains?purchase_cancel=true';

            // 2. Create Stripe Session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => "Domain Registration: {$request->domain}",
                            'description' => "Registration for {$request->years} year(s)",
                        ],
                        'unit_amount' => (int)($request->price * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'domain_order_id' => $order->id,
                    'tenant_id' => $tenantId,
                    'type' => 'domain_purchase'
                ]
            ]);

            // 3. Create central Payment record
            $payment = \App\Models\Payment::create([
                'tenant_id' => $tenant->id,
                'module_id' => null, // Explicitly null for domain-related payment
                'amount' => $request->price,
                'currency' => 'USD',
                'payment_method' => 'stripe',
                'payment_status' => 'pending',
                'stripe_session_id' => $session->id,
            ]);

            // 4. Update order with session/payment reference
            $order->update(['payment_id' => $session->id]);

            // 5. Create PENDING Invoice immediately
            $this->createInvoiceForOrder($order, 'pending');

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $session->url,
                    'order_id' => $order->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Domain purchase initiation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify domain purchase after Stripe redirect
     */
    public function verifyPurchase(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $sessionId = $request->session_id;
            $stripeSecret = config('services.stripe.secret');
            \Stripe\Stripe::setApiKey($stripeSecret);

            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed or failed',
                    'status' => $session->payment_status
                ], 400);
            }

            // Check metadata to find the order
            $orderId = $session->metadata->domain_order_id;
            $order = DomainOrder::findOrFail($orderId);

            if ($order->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Domain already verified',
                    'data' => ['order' => $order]
                ]);
            }

            // Update Payment record
            $payment = \App\Models\Payment::where('stripe_session_id', $sessionId)->first();
            if ($payment) {
                $payment->update([
                    'payment_status' => 'completed',
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'paid_at' => now(),
                ]);

                // 4.5 Save Payment Method for future use
                try {
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
                    if ($paymentIntent->payment_method) {
                        $stripePm = \Stripe\PaymentMethod::retrieve($paymentIntent->payment_method);
                        
                        $brand = strtolower($stripePm->card->brand ?? '');
                        $last4 = $stripePm->card->last4 ?? '';
                        $expMonth = $stripePm->card->exp_month ?? null;
                        $expYear = $stripePm->card->exp_year ?? null;

                        // Check if this payment method already exists for this tenant
                        $existingPm = \App\Models\PaymentMethod::where('stripe_payment_method_id', $stripePm->id)->first();
                        
                        if (!$existingPm) {
                            // Better check: Is there already an active card with the same details for this tenant?
                            $existingPm = \App\Models\PaymentMethod::where('tenant_id', $payment->tenant_id)
                                ->where('brand', $brand)
                                ->where('last4', $last4)
                                ->where('exp_month', $expMonth)
                                ->where('exp_year', $expYear)
                                ->where('is_active', true)
                                ->first();
                            
                            if ($existingPm) {
                                $existingPm->update(['stripe_payment_method_id' => $stripePm->id]);
                            }
                        }
                        
                        if (!$existingPm) {
                            \App\Models\PaymentMethod::create([
                                'tenant_id' => $payment->tenant_id,
                                'stripe_payment_method_id' => $stripePm->id,
                                'type' => $stripePm->type,
                                'brand' => $brand,
                                'last4' => $last4,
                                'exp_month' => $expMonth,
                                'exp_year' => $expYear,
                                'is_default' => \App\Models\PaymentMethod::where('tenant_id', $payment->tenant_id)->where('is_active', true)->count() === 0,
                                'is_active' => true,
                            ]);
                            Log::info("Saved new payment method {$stripePm->id} for tenant {$payment->tenant_id}");
                        }
                    }
                } catch (\Exception $pmErr) {
                    Log::error("Failed to save payment method during domain verification: " . $pmErr->getMessage());
                    // Don't fail the whole process if PM saving fails
                }
            }

            // 5. Trigger registration service with tenant's contact info
            $tenantIdAttr = $request->attributes->get('tenant_id') ?? $order->tenant_id;
            $tenant = Tenant::where('tenant_id', $tenantIdAttr)->first();

            if (!$tenant) {
                Log::error("Tenant not found for ID {$tenantIdAttr} during domain purchase verification");
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found. Cannot proceed with registration.'
                ], 404);
            }

            $contactInfo = [
                'first_name' => $tenant->admin_name ?? 'Merchant',
                'last_name'  => 'Owner',
                'address'    => $tenant->address ?? 'Main Street',
                'city'       => $tenant->city ?? 'Dhaka',
                'state'      => 'NY',
                'zip'        => '10001',
                'country'    => $tenant->country ?? 'Bangladesh',
                'phone'      => $tenant->phone ?? '+880.1711111111',
                'email'      => $tenant->admin_email ?? $tenant->email,
            ];

            $tenantDomain = null;
            try {
                $registrationService = app(\App\Services\DomainRegistrationService::class);
                $tenantDomain = $registrationService->registerDomain(
                    $order->tenant_id,
                    $order->domain,
                    $order->registration_years,
                    $contactInfo
                );

                // Link the registered TenantDomain back to this order
                if ($tenantDomain && isset($tenantDomain->id)) {
                    $order->update(['tenant_domain_id' => $tenantDomain->id]);
                }
            } catch (\Exception $regErr) {
                // Registration API failed (e.g. Namecheap sandbox) but payment was confirmed.
                // Mark the order as 'paid' with a calculated expiry so it is no longer stuck in 'pending'.
                Log::warning("Domain registration failed after successful payment for order {$order->id}: " . $regErr->getMessage());
                $years = $order->registration_years ?? 1;
                $order->update([
                    'status'      => 'paid',
                    'expiry_date' => now()->addYears($years),
                ]);
            }

            // Mark existing Invoice as PAID
            $invoice = null;
            if ($payment) {
                $invoice = \App\Models\Invoice::where('payment_id', $payment->id)->first();
                if ($invoice) {
                    $invoice->update(['status' => 'paid']);
                } else {
                    // Fallback: Create invoice if it was missed during 'purchase' initiation
                    $invoice = $this->createInvoiceForOrder($order, 'paid');
                }
            } else {
                // Fallback: Try to find invoice using notes
                $invoice = \App\Models\Invoice::where('notes', 'LIKE', "%{$order->domain}%")
                    ->where('status', 'pending')
                    ->first();
                if ($invoice) {
                    $invoice->update(['status' => 'paid']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Domain registration initiated successfully',
                'data' => [
                    'order' => $order->fresh(),
                    'domain' => $tenantDomain,
                    'invoice' => $invoice
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Domain purchase verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Repay an unpaid domain order
     */
    public function repay(string $orderId)
    {
        try {
            $order = DomainOrder::findOrFail($orderId);
            
            if ($order->status === 'completed') {
                return response()->json(['success' => false, 'message' => 'Order already completed'], 400);
            }

            // Set Stripe API key
            $stripeSecret = config('services.stripe.secret');
            \Stripe\Stripe::setApiKey($stripeSecret);

            $baseUrl = explode(',', config('app.frontend_url'))[0];
            $successUrl = rtrim($baseUrl, '/') . '/domains?purchase_success=true';
            $cancelUrl  = rtrim($baseUrl, '/') . '/domains?purchase_cancel=true';

            // Create New Stripe Session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => "Domain Registration (Repay): {$order->domain}",
                            'description' => "Registration for {$order->registration_years} year(s)",
                        ],
                        'unit_amount' => (int)($order->amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'domain_order_id' => $order->id,
                    'tenant_id' => $order->tenant_id,
                    'type' => 'domain_purchase'
                ]
            ]);

            // Update central Payment record
            $payment = \App\Models\Payment::where('stripe_session_id', $order->payment_id)->first();
            if ($payment) {
                $payment->update(['stripe_session_id' => $session->id]);
            }

            // Update order with new session reference
            $order->update(['payment_id' => $session->id]);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $session->url,
                    'order_id' => $order->id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create invoice for a domain order
     */
    protected function createInvoiceForOrder(DomainOrder $order, string $status = 'paid')
    {
        try {
            // Find tenant numeric ID
            $tenant = \App\Models\Tenant::where('tenant_id', $order->tenant_id)->first();
            if (!$tenant) {
                Log::error("Tenant not found for slug {$order->tenant_id} during invoice creation");
                return null;
            }

            $payment = \App\Models\Payment::where('stripe_session_id', $order->payment_id)->first();
            
            // Use a more robust invoice numbering to avoid collisions
            $year = date('Y');
            $tenantInvoiceCount = \App\Models\Invoice::where('tenant_id', $tenant->id)->count();
            $invoiceNumber = 'DOM-' . $year . '-' . str_pad($tenantInvoiceCount + 1, 6, '0', STR_PAD_LEFT);
            
            // Ensure uniqueness if collision occurs (e.g. concurrent requests)
            $exists = \App\Models\Invoice::where('invoice_number', $invoiceNumber)->exists();
            if ($exists) {
                $invoiceNumber .= '-' . strtoupper(\Illuminate\Support\Str::random(4));
            }

            $invoice = \App\Models\Invoice::create([
                'tenant_id' => $tenant->id,
                'payment_id' => $payment ? $payment->id : null,
                'module_id' => null,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'due_date' => now(),
                'subscription_type' => 'domain_registration',
                'subtotal' => $order->amount,
                'tax' => 0.00,
                'discount' => 0.00,
                'total' => $order->amount,
                'status' => $status,
                'notes' => "Domain Registration: {$order->domain} for {$order->registration_years} year(s)",
                'metadata' => [
                    'item_type' => 'domain',
                    'domain_name' => $order->domain,
                    'years' => $order->registration_years,
                    'price' => $order->amount,
                ],
            ]);

            Log::info("Domain Invoice created: {$invoiceNumber} for order {$order->id}");
            return $invoice;
        } catch (\Exception $e) {
            Log::error("Failed to create invoice for domain order {$order->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync order status with Namecheap
     */
    public function syncOrder(Request $request, $id)
    {
        $order = DomainOrder::findOrFail($id);
        
        try {
            $info = $this->namecheapService->getWhois($order->domain);
            
            if (!empty($info['expires'])) {
                try {
                    $expiry = new \DateTime($info['expires']);
                    $order->update([
                        'expiry_date' => $expiry,
                        'status'      => 'completed',
                    ]);
                } catch (\Exception $e) {
                    // Ignore date parse error
                }
            } else {
                // WHOIS didn't return an expiry (e.g., sandbox / newly registered).
                // If the Stripe payment was confirmed, at least mark the order as 'paid'
                // with a calculated expiry so it is no longer stuck in 'pending'.
                if ($order->status === 'pending' || $order->status === 'failed') {
                    $stripePayment = \App\Models\Payment::where('stripe_session_id', $order->payment_id)
                        ->where('payment_status', 'completed')
                        ->first();
                    if ($stripePayment) {
                        $years = $order->registration_years ?? 1;
                        $order->update([
                            'status'      => 'paid',
                            'expiry_date' => now()->addYears($years),
                        ]);
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Order synced successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew a domain order
     */
    public function renewOrder(Request $request, $id)
    {
        $order = DomainOrder::findOrFail($id);
        $years = (int) $request->input('years', 1);

        try {
            $result = $this->registrationService->renewDomain($order->domain, $years);

            return response()->json([
                'success' => true,
                'message' => 'Domain renewed successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Renewal failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
