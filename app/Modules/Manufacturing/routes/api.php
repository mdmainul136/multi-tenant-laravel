<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Manufacturing\Controllers\ManufacturingController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\CheckModuleAccess;

Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class, 
    'module.access:manufacturing',
    'quota.enforce'
])
    ->prefix('manufacturing')
    ->group(function () {

    Route::controller(ManufacturingController::class)->group(function () {
        Route::get('/boms',           'boms');
        Route::post('/boms',          'storeBom');
        Route::get('/orders',         'orders');
        Route::post('/orders',        'storeOrder');
        Route::post('/orders/{id}/start',    'startOrder');
        Route::post('/orders/{id}/complete', 'completeOrder');
        Route::get('/stats',         'stats');
    });
});
