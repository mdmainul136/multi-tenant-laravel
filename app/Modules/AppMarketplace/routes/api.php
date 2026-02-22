<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:app-marketplace',
        'quota.enforce'
    ])->prefix('app-marketplace')->group(function () {
        Route::get('/list', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    ['name' => 'Mailchimp Integration', 'installed' => true],
                    ['name' => 'Slack Alerts', 'installed' => false]
                ]
            ]);
        });
    });
});
