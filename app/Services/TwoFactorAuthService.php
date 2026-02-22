<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/**
 * TwoFactorAuthService
 *
 * Manages TOTP-based Two-Factor Authentication for staff accounts.
 * Uses the pragmarx/google2fa package.
 */
class TwoFactorAuthService
{
    private Google2FA $tfa;

    public function __construct()
    {
        $this->tfa = new Google2FA();
    }

    /**
     * Generate a new 2FA secret for a user.
     */
    public function generateSecret(): string
    {
        return $this->tfa->generateSecretKey();
    }

    /**
     * Generate the QR code provisioning URI.
     */
    public function getQrCodeUrl(string $email, string $secret): string
    {
        $appName = config('app.name', 'MultiTenant');
        return $this->tfa->getQRCodeUrl($appName, $email, $secret);
    }

    /**
     * Verify a TOTP code against the user's secret.
     */
    public function verify(string $secret, string $code): bool
    {
        return $this->tfa->verifyKey($secret, $code);
    }

    /**
     * Enable 2FA for a user.
     */
    public function enable(int $userId, string $secret): void
    {
        DB::table('users')->where('id', $userId)->update([
            'two_factor_secret'     => encrypt($secret),
            'two_factor_enabled'    => true,
            'two_factor_confirmed_at' => now(),
            'updated_at'            => now(),
        ]);

        ActivityLogService::log('enabled_2fa', 'auth', 'user', $userId);
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable(int $userId): void
    {
        DB::table('users')->where('id', $userId)->update([
            'two_factor_secret'       => null,
            'two_factor_enabled'      => false,
            'two_factor_confirmed_at' => null,
            'updated_at'              => now(),
        ]);

        ActivityLogService::log('disabled_2fa', 'auth', 'user', $userId);
    }

    /**
     * Check if a user has 2FA enabled.
     */
    public function isEnabled(int $userId): bool
    {
        return (bool) DB::table('users')
            ->where('id', $userId)
            ->value('two_factor_enabled');
    }

    /**
     * Get the decrypted secret for verification.
     */
    public function getSecret(int $userId): ?string
    {
        $encrypted = DB::table('users')
            ->where('id', $userId)
            ->value('two_factor_secret');

        return $encrypted ? decrypt($encrypted) : null;
    }
}
