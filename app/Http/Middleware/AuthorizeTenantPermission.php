<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeTenantPermission
{
    /**
     * Handle an incoming request and check for tenant-specific permissions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // 1. Ensure user is authenticated via AuthenticateToken middleware
        $userId = $request->get('user_id');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Authentication required',
            ], 401);
        }

        // 2. Resolve the user from the current (tenant) database connection
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User not found in this tenant context',
            ], 401);
        }

        // 3. Check permission using the HasRoles trait
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden - You do not have the '{$permission}' permission.",
                'permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
