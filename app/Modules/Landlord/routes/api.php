<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:landlord',
        'quota.enforce'
    ])->prefix('landlord')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_properties' => 15,
                    'occupied_units' => 12,
                    'rent_due' => 8400,
                    'open_maintenance' => 3
                ]
            ]);
        });
    });
});
