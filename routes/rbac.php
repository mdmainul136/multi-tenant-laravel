<?php

use App\Http\Controllers\RbacController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RBAC & Security API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'identify.tenant'])->prefix('admin/rbac')->group(function () {

    // ── Roles ────────────────────────────────────────────
    Route::get('/roles',           [RbacController::class, 'roles']);
    Route::post('/roles',          [RbacController::class, 'createRole']);
    Route::put('/roles/{id}',      [RbacController::class, 'updateRole']);
    Route::delete('/roles/{id}',   [RbacController::class, 'deleteRole']);

    // ── Permissions ──────────────────────────────────────
    Route::get('/permissions',     [RbacController::class, 'permissions']);

    // ── User Assignment ──────────────────────────────────
    Route::post('/users/{userId}/roles',       [RbacController::class, 'assignUserRoles']);
    Route::post('/users/{userId}/permissions', [RbacController::class, 'assignUserPermissions']);

    // ── Activity Logs ────────────────────────────────────
    Route::get('/activity-logs',   [RbacController::class, 'activityLogs']);
});

Route::middleware(['auth:sanctum'])->prefix('auth/2fa')->group(function () {

    // ── Two-Factor Auth ──────────────────────────────────
    Route::post('/setup',    [RbacController::class, 'setup2FA']);
    Route::post('/confirm',  [RbacController::class, 'confirm2FA']);
    Route::post('/verify',   [RbacController::class, 'verify2FA']);
    Route::post('/disable',  [RbacController::class, 'disable2FA']);
});
