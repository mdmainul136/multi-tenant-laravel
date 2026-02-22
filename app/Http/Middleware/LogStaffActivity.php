<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every authenticated request for audit purposes.
 *
 * Usage: Apply globally or to specific route groups.
 */
class LogStaffActivity
{
    /** Actions that should NOT be logged (to avoid noise) */
    private const SKIP_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log mutating actions by default
        if (in_array($request->method(), self::SKIP_METHODS)) {
            return $response;
        }

        $user = $request->user();
        if (!$user) return $response;

        // Determine module from route prefix
        $prefix = $request->route()?->getPrefix() ?? '';
        $module = explode('/', trim($prefix, '/'))[0] ?? 'general';

        ActivityLogService::log(
            action:     strtolower($request->method()),
            module:     $module,
            resource:   $request->route()?->getName() ?? $request->path(),
            resourceId: null,
            details:    [
                'url'    => $request->fullUrl(),
                'status' => $response->getStatusCode(),
            ]
        );

        return $response;
    }
}
