<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Ecommerce\Controllers\ProductController;
use App\Modules\Ecommerce\Controllers\CategoryController;
use App\Modules\Ecommerce\Controllers\OrderController;
use App\Modules\Ecommerce\Controllers\EcommerceDashboardController;
use App\Modules\Ecommerce\Controllers\StoreController;
use App\Modules\Ecommerce\Controllers\ReturnController;
use App\Modules\Ecommerce\Controllers\TaxCurrencyController;
use App\Modules\Ecommerce\Controllers\ReportsController;
use App\Modules\Ecommerce\Controllers\ProductImageController;
use App\Modules\Ecommerce\Controllers\WalletController;
use App\Modules\Ecommerce\Controllers\RefundController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([IdentifyTenant::class])->group(function () {

    // ──────────────────────────────────────────────────────────────────────
    // PUBLIC STOREFRONT (No Auth)
    // ──────────────────────────────────────────────────────────────────────
    Route::prefix('store')->controller(StoreController::class)->group(function () {
        Route::get('/products',              'index');
        Route::get('/products/featured',     'featured');
        Route::get('/products/{identifier}', 'show');
        Route::get('/products/{productId}/reviews', [\App\Modules\Ecommerce\Controllers\ReviewController::class, 'index']);
        Route::get('/products/{productId}/reviews/stats', [\App\Modules\Ecommerce\Controllers\ReviewController::class, 'stats']);
        Route::get('/categories',            'categories');
        Route::get('/settings',              [\App\Modules\Ecommerce\Controllers\TenantSettingsController::class, 'show']);
    });

    // Legacy alias
    Route::prefix('ecommerce/store')->controller(StoreController::class)->group(function () {
        Route::get('/products',              'index');
        Route::get('/products/featured',     'featured');
        Route::get('/products/{identifier}', 'show');
        Route::get('/categories',            'categories');
    });

    // ──────────────────────────────────────────────────────────────────────
    // AUTHENTICATED ECOMMERCE ADMIN ROUTES
    // ──────────────────────────────────────────────────────────────────────
    Route::middleware([
        AuthenticateToken::class, 
        'module.access:ecommerce', 
        'quota.enforce'
    ])->prefix('ecommerce')->group(function () {

        // ── Dashboard Statistics ───────────────────────────────────────────────
        Route::get('/stats', [EcommerceDashboardController::class, 'stats'])
            ->middleware('tenant.permission:view-reports');

        // ── Products ──────────────────────────────────────────────────────
        Route::prefix('products')->group(function () {
            Route::get('/',       [ProductController::class, 'index']);
            Route::post('/',      [ProductController::class, 'store']);
            Route::get('/{id}',   [ProductController::class, 'show']);
            Route::put('/{id}',   [ProductController::class, 'update']);
            Route::delete('/{id}',[ProductController::class, 'destroy']);

            // ── Product Image Gallery ──────────────────────────────────────
            Route::prefix('{productId}/images')->controller(ProductImageController::class)->group(function () {
                Route::get('/',                 'index');
                Route::post('/',                'store');
                Route::post('/url',             'addFromUrl');
                Route::post('/reorder',         'reorder');
                Route::put('/{imageId}',        'update');
                Route::delete('/{imageId}',     'destroy');
                Route::post('/{imageId}/primary','setPrimary');
            });
        });

        // ── Categories ───────────────────────────────────────────────────
        Route::prefix('categories')->controller(CategoryController::class)->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Orders ───────────────────────────────────────────────────────
        Route::prefix('orders')->controller(OrderController::class)->group(function () {
            Route::get('/',            'index');
            Route::post('/',           'store');
            Route::get('/{id}',        'show');
            Route::put('/{id}/status', 'updateStatus');
        });

        // ── Returns & Refunds (Process management) ────────────────────────
        Route::prefix('returns')->controller(ReturnController::class)->group(function () {
            Route::get('/stats',         'stats');
            Route::get('/',              'index');
            Route::post('/',             'store');
            Route::get('/{id}',          'show');
            Route::post('/{id}/approve', 'approve');
            Route::post('/{id}/reject',  'reject');
            Route::post('/{id}/refund',  'processRefund');
        });

        // ── Multi-Currency & Tax ──────────────────────────────────────────
        Route::prefix('tax-currency')->group(function () {
            Route::get('/stats', [TaxCurrencyController::class, 'dashboardStats']);

            Route::prefix('tax')->controller(TaxCurrencyController::class)->group(function () {
                Route::get('/summary', 'taxSummary');
                Route::get('/',        'getTaxConfigs');
                Route::post('/',       'storeTax');
                Route::put('/{id}',    'updateTax');
                Route::delete('/{id}', 'destroyTax');
            });

            Route::prefix('currencies')->controller(TaxCurrencyController::class)->group(function () {
                Route::post('/convert',       'convert');
                Route::get('/',               'getCurrencies');
                Route::post('/',              'storeCurrency');
                Route::put('/{id}',           'updateCurrency');
                Route::post('/{id}/default',  'setDefaultCurrency');
            });
        });

        // ── Reports & Analytics ───────────────────────────────────────────
        Route::prefix('reports')->controller(ReportsController::class)->group(function () {
            Route::get('/sales',     'sales');
            Route::get('/inventory', 'inventory');
            Route::get('/customers', 'customers');
        });


        // ── Wallets & Refunds ──────────────────────────────────────────────
        Route::prefix('wallets')->controller(WalletController::class)->group(function () {
            Route::get('/{customerId}', 'show');
            Route::post('/{customerId}/adjust', 'adjust');
        });

        Route::prefix('refunds')->controller(RefundController::class)->group(function () {
            Route::get('/',            'index');
            Route::post('/',           'store');
            Route::post('/{id}/approve', 'approve');
            Route::post('/{id}/reject',  'reject');
        });

        // ── Custom Domains ───────────────────────────────────────────────
        Route::prefix('domains')->controller(\App\Modules\Ecommerce\Controllers\TenantDomainController::class)->group(function () {
            Route::get('/',              'index');
            Route::post('/',             'store');
            Route::post('/{id}/verify',  'verify');
            Route::post('/{id}/primary', 'setPrimary');
            Route::delete('/{id}',       'destroy');
        });

    });
});
