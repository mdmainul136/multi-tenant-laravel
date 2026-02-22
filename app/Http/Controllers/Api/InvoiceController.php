<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    /**
     * Get all invoices for tenant
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $status = $request->query('status'); // paid, pending, all

            $query = Invoice::with(['module', 'payment'])
                ->where('tenant_id', $tenant->id)
                ->orderBy('invoice_date', 'desc');

            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            $invoices = $query->get()->map(function ($invoice) {
                $meta = is_array($invoice->metadata) ? $invoice->metadata : [];
                $domainName = $meta['domain_name'] ?? null;
                $regYears   = $meta['years'] ?? null;

                return [
                    'id'               => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'invoice_date'     => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date'         => $invoice->due_date?->format('Y-m-d'),
                    'module_name'      => $invoice->module
                        ? $invoice->module->module_name
                        : ($invoice->subscription_type === 'domain_registration'
                            ? 'Domain Registration'
                            : 'Miscellaneous'),
                    'domain_name'      => $domainName,          // e.g. niharstore1.com
                    'registration_years' => $regYears,          // e.g. 1
                    'subscription_type'  => $invoice->subscription_type,
                    'subtotal'         => $invoice->subtotal,
                    'tax'              => $invoice->tax,
                    'discount'         => $invoice->discount,
                    'total'            => $invoice->total,
                    'status'           => $invoice->status,
                    'is_overdue'       => $invoice->isOverdue(),
                    'metadata'         => $invoice->metadata,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch invoices: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single invoice details
     */
    public function show(Request $request, $id)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $invoice = Invoice::with(['module', 'payment', 'tenant'])
                ->where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $meta = is_array($invoice->metadata) ? $invoice->metadata : [];

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'                 => $invoice->id,
                    'invoice_number'     => $invoice->invoice_number,
                    'invoice_date'       => $invoice->invoice_date->format('Y-m-d'),
                    'due_date'           => $invoice->due_date?->format('Y-m-d'),
                    'module'             => [
                        'name'        => $invoice->module ? $invoice->module->module_name : ($invoice->subscription_type === 'domain_registration' ? 'Domain Registration' : 'Miscellaneous'),
                        'description' => $invoice->module ? $invoice->module->description : 'Service registration',
                    ],
                    'tenant'             => [
                        'name'  => $invoice->tenant?->tenant_name ?? 'Tenant',
                        'email' => $invoice->tenant?->admin_email ?? 'N/A',
                    ],
                    'subscription_type'  => $invoice->subscription_type,
                    'domain_name'        => $meta['domain_name'] ?? null,
                    'registration_years' => $meta['years'] ?? null,
                    'subtotal'           => $invoice->subtotal,
                    'tax'                => $invoice->tax,
                    'discount'           => $invoice->discount,
                    'total'              => $invoice->total,
                    'status'             => $invoice->status,
                    'notes'              => $invoice->notes,
                    'payment_id'         => $invoice->payment_id,
                    'metadata'           => $invoice->metadata,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch invoice: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Download invoice PDF
     */
    /**
     * Download invoice PDF
     */
    public function download(Request $request, $id)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $invoice = Invoice::with(['module', 'payment', 'tenant'])
                ->where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', compact('invoice'));
            
            return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');

        } catch (\Exception $e) {
            Log::error('Failed to download invoice: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a Stripe Checkout Session to pay an unpaid invoice
     */
    public function pay(Request $request, $id)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant   = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $invoice = Invoice::with(['payment'])
                ->where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            if ($invoice->status === 'paid') {
                return response()->json(['success' => false, 'message' => 'Invoice is already paid'], 400);
            }

            // If this is a domain invoice and has a linked DomainOrder, reuse the repay endpoint logic
            if ($invoice->subscription_type === 'domain_registration') {
                $domainOrder = \App\Models\DomainOrder::where('tenant_id', $tenantId)
                    ->where('amount', $invoice->total)
                    ->whereIn('status', ['pending', 'failed', 'paid'])
                    ->latest()
                    ->first();

                if ($domainOrder) {
                    // Delegate to repay logic
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $baseUrl    = explode(',', config('app.frontend_url'))[0];
                    $session    = \Stripe\Checkout\Session::create([
                        'payment_method_types' => ['card'],
                        'line_items'           => [[
                            'price_data' => [
                                'currency'     => 'usd',
                                'product_data' => ['name' => "Domain Re-Payment: {$domainOrder->domain}"],
                                'unit_amount'  => (int)($domainOrder->amount * 100),
                            ],
                            'quantity'   => 1,
                        ]],
                        'mode'        => 'payment',
                        'success_url' => rtrim($baseUrl, '/') . '/domains?purchase_success=true&session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url'  => rtrim($baseUrl, '/') . '/billing',
                        'metadata'    => [
                            'domain_order_id' => $domainOrder->id,
                            'tenant_id'       => $tenantId,
                            'type'            => 'domain_purchase',
                        ],
                    ]);

                    // Update the order payment reference
                    $domainOrder->update(['payment_id' => $session->id]);

                    return response()->json(['success' => true, 'data' => ['url' => $session->url]]);
                }
            }

            // Generic invoice payment via Stripe
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $baseUrl = explode(',', config('app.frontend_url'))[0];

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => "Invoice #{$invoice->invoice_number}"],
                        'unit_amount'  => (int)($invoice->total * 100),
                    ],
                    'quantity'   => 1,
                ]],
                'mode'        => 'payment',
                'success_url' => rtrim($baseUrl, '/') . '/billing?paid=true',
                'cancel_url'  => rtrim($baseUrl, '/') . '/billing',
                'metadata'    => [
                    'invoice_id' => $invoice->id,
                    'tenant_id'  => $tenantId,
                    'type'       => 'invoice_payment',
                ],
            ]);

            // Create / update Payment record
            $payment = \App\Models\Payment::create([
                'tenant_id'        => $tenant->id,
                'module_id'        => $invoice->module_id,
                'amount'           => $invoice->total,
                'currency'         => 'USD',
                'payment_method'   => 'stripe',
                'payment_status'   => 'pending',
                'stripe_session_id'=> $session->id,
            ]);

            $invoice->update(['payment_id' => $payment->id]);

            return response()->json(['success' => true, 'data' => ['url' => $session->url]]);

        } catch (\Exception $e) {
            Log::error("Invoice pay failed for invoice {$id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to initiate payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create invoice (internal use)
     */
    public static function createInvoice($tenantId, $moduleId, $paymentId, $subscriptionType, $amount)
    {
        try {
            $invoiceNumber = Invoice::generateInvoiceNumber();
            
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'module_id' => $moduleId,
                'payment_id' => $paymentId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'due_date' => now(), // Immediate for prepaid
                'subscription_type' => $subscriptionType,
                'subtotal' => $amount,
                'tax' => 0, // Can be calculated based on location
                'discount' => 0,
                'total' => $amount,
                'status' => 'paid', // Marked as paid immediately after successful payment
            ]);

            Log::info("Invoice created: {$invoiceNumber} for tenant {$tenantId}");

            return $invoice;

        } catch (\Exception $e) {
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return null;
        }
    }
}
