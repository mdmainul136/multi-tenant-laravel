<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require a specific role to access a route.
 *
 * Usage in routes:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,manager')  // any of
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You do not have the required role.',
            'required' => $roles,
        ], 403);
    }
}
