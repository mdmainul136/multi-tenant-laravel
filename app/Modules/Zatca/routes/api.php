<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:zatca',
        'quota.enforce'
    ])->prefix('zatca')->group(function () {
        Route::get('/status', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'compliance_status' => 'Pending',
                    'submission_count' => 0,
                    'error_logs' => []
                ]
            ]);
        });
    });
});
