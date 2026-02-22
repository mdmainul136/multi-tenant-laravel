<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/rbac.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('app/Modules/Marketing/routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dynamic CORS for tenant subdomains and custom domains
        $middleware->prepend(\App\Http\Middleware\DynamicCors::class);

        // Alias for billing, quota & RBAC
        $middleware->alias([
            'module.access'      => \App\Http\Middleware\CheckModuleAccess::class,
            'quota.enforce'      => \App\Http\Middleware\EnforceDatabaseQuota::class,
            'tenant.permission'  => \App\Http\Middleware\AuthorizeTenantPermission::class,
            'permission'         => \App\Http\Middleware\RequirePermission::class,
            'role'               => \App\Http\Middleware\RequireRole::class,
            'module.rbac'        => \App\Http\Middleware\RequireModuleAccess::class,
            '2fa'                => \App\Http\Middleware\Enforce2FA::class,
            'log.activity'       => \App\Http\Middleware\LogStaffActivity::class,
            'tenant.url'         => \App\Http\Middleware\IdentifyTenantByUrl::class,
            'advanced_quota'     => \App\Http\Middleware\EnforceAdvancedQuota::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
