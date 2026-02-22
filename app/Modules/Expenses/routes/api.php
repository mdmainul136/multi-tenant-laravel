<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:expenses',
        'quota.enforce'
    ])->prefix('expenses')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_burn' => 12500,
                    'pending_approvals' => 4,
                    'top_category' => 'Marketing',
                    'available_budget' => 50000
                ]
            ]);
        });
    });
});
