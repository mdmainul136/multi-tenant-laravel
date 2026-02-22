<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\SupplierController;
use App\Modules\Inventory\Controllers\PurchaseOrderController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\CheckModuleAccess;

Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class, 
    'module.access:inventory',
    'quota.enforce'
])
    ->prefix('inventory')
    ->group(function () {

    // ── Suppliers ──────────────────────────────────────────────────────────
    Route::prefix('suppliers')->controller(SupplierController::class)->group(function () {
        Route::get('/stats',   'stats');
        Route::get('/',        'index');
        Route::post('/',       'store');
        Route::get('/{id}',    'show');
        Route::put('/{id}',    'update');
        Route::delete('/{id}', 'destroy');
    });

    // ── Purchase Orders ────────────────────────────────────────────────────
    Route::prefix('purchase-orders')->controller(PurchaseOrderController::class)->group(function () {
        Route::get('/stats',         'stats');
        Route::get('/',              'index');
        Route::post('/',             'store');
        Route::get('/{id}',          'show');
        Route::put('/{id}',          'update');
        Route::delete('/{id}',       'destroy');
        Route::put('/{id}/status',   'updateStatus');
        Route::post('/{id}/receive', 'receive');
    });

    // ── Internal Inventory (Warehouse) ───────────────────────────────
    Route::prefix('warehouse')->controller(\App\Modules\Inventory\Controllers\InventoryController::class)->group(function () {
        Route::get('/list',          'warehouses'); // Aliased for clarity
        Route::post('/create',       'storeWarehouse');
        Route::get('/alerts',        'alerts');
        Route::get('/history/{id}',  'history');
        Route::post('/adjust',       'adjust');
        Route::post('/transfer',     'transfer');
    });
});
