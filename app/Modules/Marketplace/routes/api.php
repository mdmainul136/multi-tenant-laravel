<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:marketplace',
        'quota.enforce'
    ])->prefix('marketplace')->group(function () {
        Route::get('/summary', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_vendors' => 5,
                    'pending_payouts' => 1200,
                    'total_commissions' => 450,
                    'active_listings' => 85
                ]
            ]);
        });
    });
});
