<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:lms',
        'quota.enforce'
    ])->prefix('lms')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_students' => 540,
                    'courses_published' => 12,
                    'completion_rate' => '78%',
                    'certificates_issued' => 125
                ]
            ]);
        });
    });
});
