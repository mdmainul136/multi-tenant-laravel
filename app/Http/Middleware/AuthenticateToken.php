<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->query('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - No token provided',
            ], 401);
        }

        try {
            // Parse and verify token
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                throw new \Exception('Invalid token format');
            }

            [$header, $payload, $signature] = $parts;

            // Verify signature
            $expectedSignature = base64_encode(
                hash_hmac('sha256', "$header.$payload", config('app.key'), true)
            );

            if ($signature !== $expectedSignature) {
                throw new \Exception('Invalid token signature');
            }

            // Decode payload
            $payloadData = json_decode(base64_decode($payload), true);

            // Check expiration
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                throw new \Exception('Token expired');
            }

            $tokenTenantId = $payloadData['tenantId'] ?? null;
            $identifiedTenantId = $request->attributes->get('tenant_id');

            // STRICT TENANT ISOLATION: 
            // Check if the tenant identified by the URL matches the tenant in the token
            if ($identifiedTenantId && $tokenTenantId && $identifiedTenantId !== $tokenTenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Access denied for this tenant',
                    'debug' => config('app.debug') ? [
                        'token_tenant' => $tokenTenantId,
                        'url_tenant' => $identifiedTenantId
                    ] : null
                ], 403);
            }

            // Attach user ID and tenant ID to request
            $request->merge([
                'user_id' => $payloadData['id'] ?? null,
                'token_tenant_id' => $tokenTenantId,
            ]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Invalid token',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 401);
        }
    }
}
