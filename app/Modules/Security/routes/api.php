<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:security',
        'quota.enforce'
    ])->prefix('security')->group(function () {
        Route::get('/logs', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    ['action' => 'login', 'user' => 'admin', 'ip' => '127.0.0.1', 'time' => now()],
                    ['action' => 'update_product', 'user' => 'staff1', 'ip' => '127.0.0.1', 'time' => now()]
                ]
            ]);
        });
    });
});
