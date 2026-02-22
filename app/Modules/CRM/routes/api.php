<?php

use Illuminate\Support\Facades\Route;
use App\Modules\CRM\Controllers\CustomerController;
use App\Modules\CRM\Controllers\LoyaltyCouponController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\CheckModuleAccess;

Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class, 
    'module.access:crm',
    'quota.enforce'
])
    ->prefix('crm')
    ->group(function () {

    // ── Customers ──────────────────────────────────────────────────────────
    Route::prefix('customers')->controller(CustomerController::class)->group(function () {
        Route::get('/',        'index');
        Route::post('/',       'store');
        Route::get('/{id}',    'show');
        Route::put('/{id}',    'update');
        Route::delete('/{id}', 'destroy');
    });

    // ── Loyalty Program ────────────────────────────────────────────────────
    Route::prefix('loyalty')->controller(LoyaltyCouponController::class)->group(function () {
        Route::get('/program',                        'getProgram');
        Route::put('/program',                        'updateProgram');
        Route::get('/stats',                          'loyaltyStats');
        Route::get('/customers',                      'getCustomerPoints');
        Route::get('/customers/{customerId}',         'getCustomerBalance');
        Route::post('/customers/{customerId}/adjust', 'adjustPoints');
    });

    // ── Coupons ────────────────────────────────────────────────────────────
    Route::prefix('coupons')->controller(LoyaltyCouponController::class)->group(function () {
        Route::post('/validate', 'validateCoupon');
        Route::get('/',          'getCoupons');
        Route::post('/',         'storeCoupon');
        Route::put('/{id}',      'updateCoupon');
        Route::delete('/{id}',   'destroyCoupon');
    });
});
