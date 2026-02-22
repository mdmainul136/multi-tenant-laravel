<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:contracts',
        'quota.enforce'
    ])->prefix('contracts')->group(function () {
        Route::get('/summary', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_contracts' => 24,
                    'expiring_soon' => 5,
                    'pending_signatures' => 3
                ]
            ]);
        });
    });
});
