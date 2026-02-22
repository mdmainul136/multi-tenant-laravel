<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ecommerce\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get wallet balance and recent transactions for a customer.
     */
    public function show($customerId)
    {
        $wallet = $this->walletService->getWallet($customerId);
        $wallet->load(['transactions' => function($query) {
            $query->latest()->take(20);
        }]);

        return response()->json([
            'success' => true,
            'data' => $wallet
        ]);
    }

    /**
     * Admin: Manually add/deduct funds.
     */
    public function adjust(Request $request, $customerId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:deposit,withdrawal',
            'description' => 'required|string|max:255'
        ]);

        try {
            if ($request->type === 'deposit') {
                $transaction = $this->walletService->deposit($customerId, $request->amount, $request->description);
            } else {
                $transaction = $this->walletService->withdraw($customerId, $request->amount, $request->description);
            }

            return response()->json([
                'success' => true,
                'message' => 'Wallet balance adjusted successfully',
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
