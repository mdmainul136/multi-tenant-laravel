<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:restaurant',
        'quota.enforce'
    ])->prefix('restaurant')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_tables' => 14,
                    'pending_orders' => 5,
                    'reservations_today' => 8,
                    'daily_revenue' => 3200
                ]
            ]);
        });
    });
});
