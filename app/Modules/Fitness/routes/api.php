<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:fitness',
        'quota.enforce'
    ])->prefix('fitness')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_members' => 85,
                    'classes_today' => 6,
                    'revenue_monthly' => 12000,
                    'trainer_availability' => 'Available'
                ]
            ]);
        });
    });
});
