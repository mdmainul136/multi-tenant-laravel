<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:education',
        'quota.enforce'
    ])->prefix('education')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_students' => 150,
                    'active_classes' => 8,
                    'attendance_rate' => '94%',
                    'fees_collected' => 25000
                ]
            ]);
        });
    });
});
