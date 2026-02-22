<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require specific permission(s) to access a route.
 *
 * Usage in routes:
 *   ->middleware('permission:ecommerce.products.create')
 *   ->middleware('permission:pos.sales.refund,pos.sales.view')  // any of
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have the required permission.',
            'required' => $permissions,
        ], 403);
    }
}
