<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:freelancer',
        'quota.enforce'
    ])->prefix('freelancer')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_projects' => 4,
                    'hours_this_week' => 32,
                    'unpaid_invoices' => 2,
                    'monthly_earnings' => 4500
                ]
            ]);
        });
    });
});
