<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:events',
        'quota.enforce'
    ])->prefix('events')->group(function () {
        Route::get('/summary', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'upcoming_events' => 4,
                    'tickets_sold' => 1250,
                    'revenue' => 45000
                ]
            ]);
        });
    });
});
