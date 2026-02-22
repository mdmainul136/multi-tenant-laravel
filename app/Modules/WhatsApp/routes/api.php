<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {
    Route::middleware([
        AuthenticateToken::class,
        'module.access:whatsapp',
        'quota.enforce'
    ])->prefix('whatsapp')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_chats' => 15,
                    'messages_sent' => 450,
                    'orders_via_chat' => 8,
                    'api_status' => 'connected'
                ]
            ]);
        });
    });
});
