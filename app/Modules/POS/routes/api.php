<?php

use Illuminate\Support\Facades\Route;
use App\Modules\POS\Controllers\PosController;

Route::middleware(['auth:sanctum', 'module.access:pos', 'identify.tenant'])->prefix('pos')->group(function () {
    Route::post('/pin-login',     [PosController::class, 'pinLogin']);
    Route::post('/sessions/open', [PosController::class, 'openSession']);
    Route::get('/sessions/current', [PosController::class, 'currentSession']);
    
    Route::post('/checkout',      [PosController::class, 'checkout']);
    Route::post('/sync',          [PosController::class, 'sync']);
    
    Route::post('/hold',          [PosController::class, 'hold']);
    Route::get('/hold',           [PosController::class, 'listHeld']);
    Route::post('/hold/{id}/recall', [PosController::class, 'recall']);
    
    Route::get('/receipt/{id}',   [PosController::class, 'receipt']);
    
    Route::get('/products/search', [PosController::class, 'searchProducts']);
    Route::get('/customers/search', [PosController::class, 'searchCustomers']);
});
