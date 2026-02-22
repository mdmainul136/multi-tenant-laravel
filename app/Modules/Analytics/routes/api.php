<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:analytics',
        'quota.enforce'
    ])->prefix('analytics')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_users_7d' => 1240,
                    'conversion_rate' => '3.5%',
                    'avg_order_value' => 245
                ]
            ]);
        });
    });
});
