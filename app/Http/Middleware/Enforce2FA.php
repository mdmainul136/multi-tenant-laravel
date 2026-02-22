<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce 2FA verification on authenticated routes.
 *
 * Usage in routes:
 *   ->middleware('2fa')
 *
 * The user must have verified their TOTP code in the current session.
 * If 2FA is enabled but not verified, returns 403.
 */
class Enforce2FA
{
    public function __construct(private TwoFactorAuthService $tfa) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // If 2FA is not enabled for this user, skip
        if (!$this->tfa->isEnabled($user->id)) {
            return $next($request);
        }

        // Check if 2FA was already verified in this session/token
        if ($request->session()?->get('2fa_verified') === true) {
            return $next($request);
        }

        // For API token auth, check the token metadata
        $token = $user->currentAccessToken();
        if ($token && ($token->abilities ?? []) && in_array('2fa-verified', $token->abilities)) {
            return $next($request);
        }

        return response()->json([
            'message'      => 'Two-factor authentication required.',
            'requires_2fa' => true,
        ], 403);
    }
}
