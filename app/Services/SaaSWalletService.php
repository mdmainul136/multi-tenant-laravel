<?php

namespace App\Services;

use App\Models\TenantWallet;
use App\Models\Ecommerce\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaaSWalletService
{
    /**
     * Debit an amount from the tenant's wallet for a specific service.
     */
    public function debit(string $tenantId, float $amount, string $serviceType, string $description = null, string $referenceId = null): bool
    {
        if ($amount <= 0) return true;

        return DB::connection('mysql')->transaction(function () use ($tenantId, $amount, $serviceType, $description, $referenceId) {
            $wallet = TenantWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = $this->initializeWallet($tenantId);
            }

            if ($wallet->balance < $amount) {
                Log::warning("Insufficient SaaS wallet balance for tenant {$tenantId}. Service: {$serviceType}");
                return false;
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->save();

            WalletTransaction::create([
                'tenant_id' => $tenantId,
                'type' => 'debit',
                'service_type' => $serviceType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'reference_id' => $referenceId,
            ]);

            return true;
        });
    }

    /**
     * Credit an amount to the tenant's wallet.
     */
    public function credit(string $tenantId, float $amount, string $serviceType, string $description = null, string $referenceId = null): void
    {
        if ($amount <= 0) return;

        DB::connection('mysql')->transaction(function () use ($tenantId, $amount, $serviceType, $description, $referenceId) {
            $wallet = TenantWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = $this->initializeWallet($tenantId);
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            WalletTransaction::create([
                'tenant_id' => $tenantId,
                'type' => 'credit',
                'service_type' => $serviceType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Check if a tenant has sufficient balance for a service.
     */
    public function hasSufficientBalance(string $tenantId, float $amount): bool
    {
        $wallet = TenantWallet::where('tenant_id', $tenantId)->first();
        if (!$wallet) return false;
        return $wallet->balance >= $amount;
    }

    /**
     * Get current wallet balance.
     */
    public function getBalance(string $tenantId): float
    {
        $wallet = TenantWallet::where('tenant_id', $tenantId)->first();
        return $wallet ? (float) $wallet->balance : 0.0;
    }

    /**
     * Initialize a wallet for a tenant if it doesn't exist.
     */
    protected function initializeWallet(string $tenantId): TenantWallet
    {
        return TenantWallet::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'balance' => 0.0,
                'locked_balance' => 0.0,
                'currency' => 'USD',
                'status' => 'active'
            ]
        );
    }
}

