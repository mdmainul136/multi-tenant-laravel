<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:national-address',
        'quota.enforce'
    ])->prefix('national-address')->group(function () {
        Route::post('/validate', function () {
            return response()->json([
                'success' => true,
                'is_valid' => true,
                'formatted_address' => 'Riyadh, 12211, KSA'
            ]);
        });
    });
});
