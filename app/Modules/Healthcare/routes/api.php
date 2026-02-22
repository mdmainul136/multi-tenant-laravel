<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:healthcare',
        'quota.enforce'
    ])->prefix('healthcare')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'appointments_today' => 18,
                    'new_patients' => 4,
                    'available_doctors' => 7,
                    'pending_reports' => 12
                ]
            ]);
        });
    });
});
