<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:maroof',
        'quota.enforce'
    ])->prefix('maroof')->group(function () {
        Route::get('/status', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_verified' => true,
                    'maroof_id' => '123456',
                    'rating' => 4.8
                ]
            ]);
        });
    });
});
