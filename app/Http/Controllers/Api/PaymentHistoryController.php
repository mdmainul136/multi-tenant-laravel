<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentHistoryController extends Controller
{
    /**
     * Get payment history for tenant
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $payments = Payment::with(['module'])
                ->where('tenant_id', $tenant->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'module_name' => $payment->module->module_name ?? 'N/A',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'payment_status' => $payment->payment_status,
                        'payment_method' => $payment->payment_method,
                        'transaction_id' => $payment->transaction_id,
                        'created_at' => $payment->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function statistics(Request $request)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $totalPaid = Payment::where('tenant_id', $tenant->id)
                ->where('payment_status', 'completed')
                ->sum('amount');

            $totalPayments = Payment::where('tenant_id', $tenant->id)
                ->count();

            $lastPayment = Payment::where('tenant_id', $tenant->id)
                ->where('payment_status', 'completed')
                ->latest()
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_paid' => $totalPaid,
                    'total_payments' => $totalPayments,
                    'last_payment' => $lastPayment ? [
                        'amount' => $lastPayment->amount,
                        'date' => $lastPayment->created_at,
                    ] : null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download invoice PDF for a specific payment.
     *
     * If an Invoice record is linked to this payment, it uses that directly.
     * Otherwise, a virtual invoice object is built from the Payment data
     * so the same Blade template renders correctly.
     */
    public function downloadInvoice(Request $request, $paymentId)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();

            $payment = Payment::with(['module', 'tenant'])
                ->where('id', $paymentId)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            // Try to find a linked Invoice record
            $invoice = Invoice::with(['module', 'payment', 'tenant'])
                ->where('payment_id', $payment->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            // If no Invoice record exists, build a virtual one from Payment data
            if (!$invoice) {
                $invoice = new Invoice([
                    'invoice_number' => 'INV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                    'invoice_date'   => $payment->created_at,
                    'due_date'       => $payment->created_at,
                    'subtotal'       => $payment->amount,
                    'tax'            => 0,
                    'discount'       => 0,
                    'total'          => $payment->amount,
                    'status'         => $payment->payment_status === 'completed' ? 'paid' : $payment->payment_status,
                    'subscription_type' => 'one-time',
                    'notes'          => 'Auto-generated from payment record',
                    'metadata'       => [
                        'item_type'   => 'module',
                        'module_name' => $payment->module->module_name ?? 'Service',
                    ],
                ]);

                // Set relationships manually so the Blade template can access them
                $invoice->setRelation('tenant', $tenant);
                $invoice->setRelation('module', $payment->module);
                $invoice->setRelation('payment', $payment);
            }

            $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));

            $filename = 'invoice-' . $invoice->invoice_number . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
