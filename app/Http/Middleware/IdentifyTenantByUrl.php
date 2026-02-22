<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\DatabaseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantByUrl
{
    public function __construct(private DatabaseManager $databaseManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->route('tenantId');

        if (!$tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant ID missing in URL'], 400);
        }

        $tenant = Tenant::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Switch to tenant database
        $this->databaseManager->switchToTenantDatabase($tenantId);

        // Attach tenant info to request
        $request->attributes->set('tenant_id', $tenant->tenant_id);

        return $next($request);
    }
}
