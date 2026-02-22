<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:sadad',
        'quota.enforce'
    ])->prefix('sadad')->group(function () {
        Route::get('/bills', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'pending_bills' => 12,
                    'total_amount' => 15000,
                    'last_sync' => now()->toDateTimeString()
                ]
            ]);
        });
    });
});
