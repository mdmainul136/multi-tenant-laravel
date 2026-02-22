<?php

namespace App\Modules\CrossBorderIOR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TenantWallet;
use App\Models\Ecommerce\WalletTransaction;
use App\Services\SaaSWalletService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function __construct(
        private SaaSWalletService $walletService,
        private PaymentGatewayService $paymentService
    ) {}

    /**
     * Get wallet stats and recent transactions for the tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant context missing'], 400);
        }

        $wallet = TenantWallet::where('tenant_id', $tenantId)->first();
        
        $stats = [
            'balance'         => $wallet ? (float) $wallet->balance : 0.0,
            'currency'        => $wallet ? $wallet->currency : 'USD',
            'total_spent'     => WalletTransaction::where('tenant_id', $tenantId)
                                    ->where('type', 'debit')
                                    ->sum('amount'),
            'total_topup'     => WalletTransaction::where('tenant_id', $tenantId)
                                    ->where('type', 'credit')
                                    ->sum('amount'),
        ];

        $transactions = WalletTransaction::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => [
                'stats'        => $stats,
                'transactions' => $transactions->items(),
                'meta'         => [
                    'current_page' => $transactions->currentPage(),
                    'last_page'    => $transactions->lastPage(),
                    'total'        => $transactions->total(),
                ]
            ]
        ]);
    }

    /**
     * Initiate a top-up request.
     */
    public function initiateTopup(Request $request): JsonResponse
    {
        $request->validate([
            'amount'   => 'required|numeric|min:5',
            'gateway'  => 'required|string|in:nagad,sslcommerz,stripe,paypal,mock',
            'currency' => 'string|size:3'
        ]);

        $tenantId = $request->attributes->get('tenant_id');
        $amount   = $request->float('amount');
        $gateway  = $request->input('gateway');

        try {
            $paymentData = $this->paymentService->initializePayment([
                'tenant_id' => $tenantId,
                'amount'    => $amount,
                'gateway'   => $gateway,
                'currency'  => $request->input('currency', 'USD'),
                'return_url'=> $request->input('return_url'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $paymentData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment gateway callback (Webhook/IPN).
     */
    public function callback(Request $request, string $gateway): JsonResponse
    {
        try {
            $verification = $this->paymentService->verifyPayment($request->all(), $gateway);

            if ($verification['status'] === 'success') {
                $this->walletService->credit(
                    $verification['tenant_id'],
                    $verification['amount'],
                    'topup',
                    "Wallet Top-up via {$gateway} (TxID: {$verification['gateway_txid']})",
                    $verification['gateway_txid']
                );

                return response()->json(['success' => true, 'message' => 'Wallet credited']);
            }

            return response()->json(['success' => false, 'message' => 'Payment validation failed'], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

