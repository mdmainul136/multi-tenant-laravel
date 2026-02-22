<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:realestate',
        'quota.enforce'
    ])->prefix('realestate')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_listings' => 42,
                    'new_leads_today' => 7,
                    'closed_deals_month' => 3,
                    'total_portfolio_value' => 12500000
                ]
            ]);
        });
    });
});
