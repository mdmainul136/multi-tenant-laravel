<?php

namespace App\Modules\Ecommerce\Services;

use App\Models\Ecommerce\Wallet;
use App\Models\Ecommerce\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get or create a wallet for a customer.
     */
    public function getWallet(int $customerId): Wallet
    {
        return Wallet::firstOrCreate(
            ['customer_id' => $customerId],
            ['balance' => 0, 'currency' => 'BDT']
        );
    }

    /**
     * Add funds to wallet.
     */
    public function deposit(int $customerId, float $amount, string $description = 'Deposit', array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $description, $metadata) {
            $wallet = $this->getWallet($customerId);
            $before = $wallet->balance;
            
            $wallet->increment('balance', $amount);
            $after = $wallet->balance;

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'metadata' => $metadata
            ]);
        });
    }

    /**
     * Add funds from loyalty points redemption.
     */
    public function depositFromPoints(int $customerId, float $amount, int $points): WalletTransaction
    {
        return $this->deposit($customerId, $amount, "Converts {$points} loyalty points to wallet balance", [
            'source' => 'loyalty_redemption',
            'points_redeemed' => $points
        ]);
    }

    /**
     * Deduct funds from wallet.
     */
    public function withdraw(int $customerId, float $amount, string $description = 'Withdrawal', array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $description, $metadata) {
            $wallet = $this->getWallet($customerId);
            
            if ($wallet->balance < $amount) {
                throw new \Exception("Insufficient wallet balance.");
            }

            $before = $wallet->balance;
            $wallet->decrement('balance', $amount);
            $after = $wallet->balance;

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'purchase',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'metadata' => $metadata
            ]);
        });
    }

    /**
     * Refund to wallet.
     */
    public function refund(int $customerId, float $amount, string $description = 'Refund to Wallet', int $referenceId = null): WalletTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $description, $referenceId) {
            $wallet = $this->getWallet($customerId);
            $before = $wallet->balance;
            
            $wallet->increment('balance', $amount);
            $after = $wallet->balance;

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'refund',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'Refund',
                'reference_id' => $referenceId,
                'description' => $description
            ]);
        });
    }
}
