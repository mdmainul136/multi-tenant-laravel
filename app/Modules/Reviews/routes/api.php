<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:reviews',
        'quota.enforce'
    ])->prefix('reviews')->group(function () {
        Route::get('/summary', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_reviews' => 450,
                    'avg_rating' => 4.7,
                    'pending_moderation' => 12
                ]
            ]);
        });
    });
});
