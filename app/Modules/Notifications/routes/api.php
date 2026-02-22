<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\CheckModuleAccess;

Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class, 
    'module.access:notifications',
    'quota.enforce'
])
    ->prefix('notifications')
    ->group(function () {

    Route::controller(NotificationController::class)->group(function () {
        Route::get('/stats',          'stats');
        Route::get('/',               'index');
        Route::post('/',              'store');
        Route::post('/broadcast',     'broadcast');
        Route::post('/from-template', 'sendFromTemplate');
        Route::post('/mark-all-read', 'markAllRead');
        Route::delete('/cleanup',     'cleanup');
        Route::post('/{id}/read',     'markRead');
        Route::delete('/{id}',        'destroy');

        // Templates
        Route::get('/templates',      'getTemplates');
        Route::post('/templates',     'storeTemplate');
        Route::put('/templates/{id}', 'updateTemplate');
    });
});
