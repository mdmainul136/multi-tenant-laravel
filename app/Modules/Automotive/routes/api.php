<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:automotive',
        'quota.enforce'
    ])->prefix('automotive')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_vehicles' => 12,
                    'active_services' => 5,
                    'test_drives_today' => 3,
                    'pending_repairs' => 2
                ]
            ]);
        });

        // Basic CRUD placeholders
        Route::get('/vehicles', function () { return response()->json(['success' => true, 'data' => []]); });
        Route::get('/services', function () { return response()->json(['success' => true, 'data' => []]); });
    });
});
