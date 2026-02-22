<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:salon',
        'quota.enforce'
    ])->prefix('salon')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'bookings_today' => 12,
                    'active_stylists' => 4,
                    'top_service' => 'Haircut',
                    'revenue_today' => 1800
                ]
            ]);
        });
    });
});
