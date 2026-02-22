<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:travel',
        'quota.enforce'
    ])->prefix('travel')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_bookings' => 24,
                    'tours_next_week' => 5,
                    'top_destination' => 'Dubai',
                    'revenue_monthly' => 45000
                ]
            ]);
        });
    });
});
