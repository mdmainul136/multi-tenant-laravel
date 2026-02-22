<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ModuleService;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $moduleKey
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $tenantId = $request->header('X-Tenant-ID') ?: $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
             return response()->json([
                'success' => false,
                'message' => 'Tenant identification required for module access check',
            ], 400);
        }

        $moduleService = app(ModuleService::class);

        if (!$moduleService->isModuleActive($tenantId, $moduleKey)) {
            return response()->json([
                'success' => false,
                'message' => "Access denied. The '{$moduleKey}' module is not active or subscribed for this tenant.",
                'module_key' => $moduleKey,
                'payment_required' => true
            ], 402); // 402 Payment Required
        }

        return $next($request);
    }
}
