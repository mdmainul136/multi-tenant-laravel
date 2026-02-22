<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require module-level access.
 *
 * Usage in routes:
 *   ->middleware('module.access:pos')
 *   ->middleware('module.access:ecommerce')
 */
class RequireModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasModuleAccess($moduleKey)) {
            return $next($request);
        }

        return response()->json([
            'message' => "You do not have access to the {$moduleKey} module.",
        ], 403);
    }
}
