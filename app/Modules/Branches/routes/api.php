<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:branches',
        'quota.enforce'
    ])->prefix('branches')->group(function () {
        Route::get('/list', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_branches' => 3,
                    'main_branch' => 'Riyadh HQ',
                    'inventory_worth_per_branch' => [
                        'Riyadh' => 450000,
                        'Jeddah' => 280000,
                        'Dammam' => 150000
                    ]
                ]
            ]);
        });
    });
});
