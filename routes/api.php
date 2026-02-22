<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;
use App\Modules\Ecommerce\Controllers\ProductController;
use App\Modules\Ecommerce\Controllers\CategoryController;
use App\Modules\Ecommerce\Controllers\OrderController;
use App\Modules\Ecommerce\Controllers\CustomerController;
use App\Modules\Ecommerce\Controllers\EcommerceDashboardController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\DomainStoreController;





/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'environment' => config('app.env'),
    ]);
});

// Stripe Webhook (no authentication required)
Route::post('/stripe/webhook', [\App\Http\Controllers\Api\PaymentController::class, 'stripeWebhook']);

// Super Admin Routes (no tenant identification required)
Route::prefix('super-admin')->group(function () {
    // Super admin authentication
    Route::post('/login', [\App\Http\Controllers\Api\SuperAdminAuthController::class, 'login']);
    
    // Protected super admin routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\SuperAdminAuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\Api\SuperAdminAuthController::class, 'me']);
        Route::post('/change-password', [\App\Http\Controllers\Api\SuperAdminAuthController::class, 'changePassword']);
        
        // Dashboard & analytics
        Route::get('/dashboard', [\App\Http\Controllers\Api\SuperAdminController::class, 'dashboard']);
        
        // Tenant management
        Route::get('/tenants', [\App\Http\Controllers\Api\SuperAdminController::class, 'tenants']);
        Route::get('/tenants/{id}', [\App\Http\Controllers\Api\SuperAdminController::class, 'tenantDetails']);
        Route::post('/tenants/{id}/approve', [\App\Http\Controllers\Api\SuperAdminController::class, 'approveTenant']);
        Route::post('/tenants/{id}/suspend', [\App\Http\Controllers\Api\SuperAdminController::class, 'suspendTenant']);
        Route::delete('/tenants/{id}', [\App\Http\Controllers\Api\SuperAdminController::class, 'deleteTenant']);
        
        // Module management
        Route::get('/modules', [\App\Http\Controllers\Api\ModuleManagementController::class, 'index']);
        Route::post('/modules/upload', [\App\Http\Controllers\Api\ModuleManagementController::class, 'upload']);
        Route::post('/modules', [\App\Http\Controllers\Api\ModuleManagementController::class, 'store']);
        Route::put('/modules/{id}', [\App\Http\Controllers\Api\ModuleManagementController::class, 'update']);
        Route::delete('/modules/{id}', [\App\Http\Controllers\Api\ModuleManagementController::class, 'destroy']);
    });
});



// Routes requiring tenant identification
Route::middleware([IdentifyTenant::class])->group(function () {
    
    // Get current identified tenant info
    Route::get('/tenants/current', [TenantController::class, 'current']);

    // Authentication Routes (tenant-specific)
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::middleware(['quota.enforce'])->group(function () {
            Route::post('/register', 'register');
            Route::post('/login', 'login');
        });
        
        // Authenticated routes
        Route::middleware([AuthenticateToken::class])->group(function () {
            Route::post('/logout', 'logout');
            Route::get('/me', 'me');
        });
    });

    // User Management Routes (requires authentication)
    Route::prefix('users')->middleware([AuthenticateToken::class, 'quota.enforce'])->controller(UserController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Module & Subscription Management Routes
    Route::prefix('modules')->middleware([AuthenticateToken::class])->controller(\App\Http\Controllers\Api\SubscriptionController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/subscribed', 'tenantModules');
        Route::post('/subscribe', 'subscribe');
        Route::delete('/{moduleKey}', 'unsubscribe');
        Route::get('/{moduleKey}/access', 'checkAccess');
    });

    // Payment Routes
    Route::prefix('payment')->controller(\App\Http\Controllers\Api\PaymentController::class)->group(function () {
        Route::post('/checkout', 'createCheckoutSession')->middleware([AuthenticateToken::class]);
        Route::post('/verify', 'verifyPayment')->middleware([AuthenticateToken::class]);
        Route::get('/{paymentId}/status', 'getPaymentStatus')->middleware([AuthenticateToken::class]);
        Route::get('/history', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'index'])->middleware([AuthenticateToken::class]);
        Route::get('/statistics', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'statistics'])->middleware([AuthenticateToken::class]);
        Route::get('/{paymentId}/invoice', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'downloadInvoice'])->middleware([AuthenticateToken::class]);
    });

    // Invoice Routes
    Route::prefix('invoices')->middleware([AuthenticateToken::class])->controller(\App\Http\Controllers\Api\InvoiceController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::get('/{id}/download', 'download');
        Route::post('/{id}/pay', 'pay');
    });

    // Payment Method Routes
    Route::prefix('payment-methods')->middleware([AuthenticateToken::class])->controller(\App\Http\Controllers\Api\PaymentMethodController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/default', 'setDefault');
    });

    // Database Analytics & Quota Routes
    Route::prefix('database')->middleware([AuthenticateToken::class])->controller(\App\Http\Controllers\Api\TenantDatabaseController::class)->group(function () {
        Route::get('/analytics', 'analytics');
        Route::get('/tables', 'tables');
        Route::get('/growth', 'growth');
        Route::get('/plans', 'plans');
    });

    // Subscription Plan Management
    Route::prefix('subscriptions')->middleware([AuthenticateToken::class])->group(function () {
        Route::get('/plans', [\App\Http\Controllers\Api\SubscriptionPlanController::class, 'index']);
        Route::get('/status', [\App\Http\Controllers\Api\SubscriptionPlanController::class, 'status']);
        Route::post('/cancel', [\App\Http\Controllers\Api\SubscriptionPlanController::class, 'cancel']);
        Route::post('/reactivate', [\App\Http\Controllers\Api\SubscriptionPlanController::class, 'reactivate']);
    });

    // Invoice & Payment History
    Route::middleware([AuthenticateToken::class])->group(function () {
        Route::get('/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\InvoiceController::class, 'show']);
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\Api\InvoiceController::class, 'download']);
        
        Route::get('/payment/history', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'index']);
        Route::get('/payment/statistics', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'statistics']);
    });

    // POS Unified System
    Route::prefix('pos')->middleware([AuthenticateToken::class])->group(function () {
        Route::post('/sync-order', [\App\Http\Controllers\Api\PosController::class, 'syncOrder']);
        Route::get('/inventory', [\App\Http\Controllers\Api\PosController::class, 'getInventory']);
        Route::post('/staff/login', [\App\Http\Controllers\Api\PosController::class, 'staffLogin']); // Placeholder
    });

    // Branch Management
    Route::prefix('branches')->middleware([AuthenticateToken::class])->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BranchController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BranchController::class, 'store']);
        Route::get('/{id}/performance', [\App\Http\Controllers\Api\BranchController::class, 'performance']);
        Route::get('/{id}/inventory', [\App\Http\Controllers\Api\BranchController::class, 'inventory']);
        Route::post('/{id}/assign-staff', [\App\Http\Controllers\Api\BranchController::class, 'assignStaff']);
    });

    // Stock Transfers
    Route::prefix('stock-transfers')->middleware([AuthenticateToken::class])->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\StockTransferController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\StockTransferController::class, 'store']);
        Route::post('/{id}/dispatch', [\App\Http\Controllers\Api\StockTransferController::class, 'dispatch']);
        Route::post('/{id}/receive', [\App\Http\Controllers\Api\StockTransferController::class, 'receive']);
    });

    // AI Generation & Settings
    Route::prefix('ai')->middleware([AuthenticateToken::class])->group(function () {
        Route::get('/config', [\App\Http\Controllers\Api\AiController::class, 'getConfig']);
        Route::post('/config', [\App\Http\Controllers\Api\AiController::class, 'updateConfig']);
        Route::post('/training', [\App\Http\Controllers\Api\AiController::class, 'updateBrainTraining']);
        Route::post('/generate-storefront', [\App\Http\Controllers\Api\AiController::class, 'generateStorefront']);
    });

    // Theme Gallery
    Route::prefix('themes')->middleware([AuthenticateToken::class])->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ThemeController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ThemeController::class, 'show']);
        Route::post('/{id}/adopt', [\App\Http\Controllers\Api\ThemeController::class, 'adopt']);
        
        // Developer Routes
        Route::prefix('developer')->group(function () {
            Route::get('/my', [\App\Http\Controllers\Api\DeveloperThemeController::class, 'index']);
            Route::post('/submit', [\App\Http\Controllers\Api\DeveloperThemeController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Api\DeveloperThemeController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\DeveloperThemeController::class, 'destroy']);
        });

        // Tenant Configuration Lifecycle
        Route::prefix('config')->group(function () {
            Route::post('/draft', [\App\Http\Controllers\Api\StorefrontConfigController::class, 'saveDraft']);
            Route::post('/publish', [\App\Http\Controllers\Api\StorefrontConfigController::class, 'publish']);
            Route::get('/history', [\App\Http\Controllers\Api\StorefrontConfigController::class, 'history']);
            Route::post('/history/{id}/rollback', [\App\Http\Controllers\Api\StorefrontConfigController::class, 'rollback']);
        });

        // Admin Theme Moderation
        Route::prefix('admin/themes')->group(function () {
            Route::get('/pending', [\App\Http\Controllers\Api\AdminThemeController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\AdminThemeController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Api\AdminThemeController::class, 'update']);
            Route::patch('/{id}/approve', [\App\Http\Controllers\Api\AdminThemeController::class, 'approve']);
            Route::patch('/{id}/reject', [\App\Http\Controllers\Api\AdminThemeController::class, 'reject']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\AdminThemeController::class, 'destroy']);
            Route::post('/{id}/capture-revenue', [\App\Http\Controllers\Api\AdminThemeController::class, 'captureRevenue']);
            Route::get('/license/check', [\App\Http\Controllers\Api\AdminThemeController::class, 'checkLicense']);
        });
    });

    // Domain Management
    Route::prefix('domains')->middleware([AuthenticateToken::class])->group(function () {
        // Static routes MUST come before /{id} wildcard routes
        Route::get('/', [DomainController::class, 'index']);
        Route::post('/', [DomainController::class, 'store']);

        // Advanced Domain Center (Store) — must be before /{id} wildcards
        Route::get('/store/orders', [DomainStoreController::class, 'orders']);
        Route::get('/store/orders/{id}', [DomainStoreController::class, 'show']);
        Route::post('/store/orders/{id}/sync', [DomainStoreController::class, 'syncOrder']);
        Route::post('/store/orders/{id}/renew', [DomainStoreController::class, 'renewOrder']);
        Route::get('/store/search', [DomainStoreController::class, 'search']);
        Route::get('/store/whois/{domain}', [DomainStoreController::class, 'whois']);
        Route::post('/store/purchase', [DomainStoreController::class, 'purchase']);
        Route::post('/store/repay/{id}', [DomainStoreController::class, 'repay']);
        Route::post('/store/verify-purchase', [DomainStoreController::class, 'verifyPurchase']);

        // Wildcard /{id} routes — must come after all static routes
        Route::post('/{id}/verify', [DomainController::class, 'verify']);
        Route::post('/{id}/primary', [DomainController::class, 'setPrimary']);
        Route::get('/{id}/dns', [DomainController::class, 'getDNSHosts']);
        Route::post('/{id}/dns', [DomainController::class, 'updateDNSHosts']);
        Route::get('/{id}/nameservers', [DomainController::class, 'getNameservers']);
        Route::post('/{id}/renew', [DomainController::class, 'renew']);
        Route::delete('/{id}', [DomainController::class, 'destroy']);
    });

    // Centralized Admin Dashboard
    Route::get('/dashboard/summary', [\App\Http\Controllers\Api\CentralDashboardController::class, 'index'])->middleware([
        AuthenticateToken::class,
        'quota.enforce'
    ]);

    // Modules are loaded dynamically below from app/Modules/*/routes/api.php
});

// Tenant Management Routes (no tenant identification required)
Route::prefix('tenants')->controller(TenantController::class)->group(function () {
    Route::post('/register', 'register');
    Route::get('/{tenantId}/status', 'checkStatus');
    Route::get('/{tenantId}', 'show');
    Route::get('/', 'index');
});


// 
// MODULE ROUTES  each module has its own routes/api.php
// 
require __DIR__ . '/../app/Modules/Ecommerce/routes/api.php';
require __DIR__ . '/../app/Modules/Inventory/routes/api.php';
require __DIR__ . '/../app/Modules/CRM/routes/api.php';
require __DIR__ . '/../app/Modules/HRM/routes/api.php';
require __DIR__ . '/../app/Modules/Finance/routes/api.php';
require __DIR__ . '/../app/Modules/Tracking/routes/api.php';
require __DIR__ . '/../app/Modules/Notifications/routes/api.php';
require __DIR__ . '/../app/Modules/Manufacturing/routes/api.php';
require __DIR__ . '/../app/Modules/CrossBorderIOR/routes/api.php';
require __DIR__ . '/../app/Modules/Automotive/routes/api.php';
require __DIR__ . '/../app/Modules/Education/routes/api.php';
require __DIR__ . '/../app/Modules/Fitness/routes/api.php';
require __DIR__ . '/../app/Modules/Freelancer/routes/api.php';
require __DIR__ . '/../app/Modules/Healthcare/routes/api.php';
require __DIR__ . '/../app/Modules/Landlord/routes/api.php';
require __DIR__ . '/../app/Modules/LMS/routes/api.php';
require __DIR__ . '/../app/Modules/Restaurant/routes/api.php';
require __DIR__ . '/../app/Modules/Salon/routes/api.php';
require __DIR__ . '/../app/Modules/Travel/routes/api.php';
require __DIR__ . '/../app/Modules/RealEstate/routes/api.php';
require __DIR__ . '/../app/Modules/Zatca/routes/api.php';
require __DIR__ . '/../app/Modules/Marketplace/routes/api.php';
require __DIR__ . '/../app/Modules/FlashSales/routes/api.php';
require __DIR__ . '/../app/Modules/WhatsApp/routes/api.php';
require __DIR__ . '/../app/Modules/Loyalty/routes/api.php';
require __DIR__ . '/../app/Modules/Branches/routes/api.php';
require __DIR__ . '/../app/Modules/Expenses/routes/api.php';
require __DIR__ . '/../app/Modules/Maroof/routes/api.php';
require __DIR__ . '/../app/Modules/NationalAddress/routes/api.php';
require __DIR__ . '/../app/Modules/Sadad/routes/api.php';
require __DIR__ . '/../app/Modules/Analytics/routes/api.php';
require __DIR__ . '/../app/Modules/AppMarketplace/routes/api.php';
require __DIR__ . '/../app/Modules/Reviews/routes/api.php';
require __DIR__ . '/../app/Modules/Security/routes/api.php';
require __DIR__ . '/../app/Modules/Contracts/routes/api.php';
require __DIR__ . '/../app/Modules/Events/routes/api.php';

//  Module Marketplace & Subscription API 
use App\Http\Controllers\Api\ModuleSubscriptionController;
use App\Http\Middleware\IdentifyTenant as IdentifyTenantMiddleware;
use App\Http\Middleware\AuthenticateToken as AuthenticateTokenMiddleware;

Route::middleware([IdentifyTenantMiddleware::class, AuthenticateTokenMiddleware::class])
    ->prefix('modules')
    ->controller(ModuleSubscriptionController::class)
    ->group(function () {
        Route::get('/',              'index');         // marketplace list
        Route::get('/my',            'myModules');     // tenant's subscriptions
        Route::get('/check/{key}',   'checkAccess');   // quick access check
        Route::get('/regions',       'regions');       // region strategy
        Route::get('/recommended',   'recommended');   // recommended modules
        Route::get('/{key}',         'show');          // module detail
        Route::get('/{key}/related', 'related');       // related modules
        Route::post('/{key}/subscribe', 'subscribe');    // subscribe
        Route::delete('/{key}/subscribe', 'unsubscribe'); // cancel
        Route::post('/{key}/trial',  'startTrial');    // 14-day trial
    });

//  SSLCommerz Payment Routes (Bangladesh Gateway) 
use App\Http\Controllers\Api\SSLCommerzController;

Route::prefix('payment/sslcommerz')->controller(SSLCommerzController::class)->group(function () {
    // Initiate requires auth
    Route::post('/initiate', 'initiate')->middleware([IdentifyTenant::class, AuthenticateToken::class]);
    Route::post('/verify',   'verify')->middleware([IdentifyTenant::class, AuthenticateToken::class]);

    // Callbacks from SSLCommerz (no auth  server/browser redirect)
    Route::get('/success',  'success')->name('sslcommerz.success');
    Route::post('/success', 'success');
    Route::post('/fail',    'fail')->name('sslcommerz.fail');
    Route::post('/cancel',  'cancel')->name('sslcommerz.cancel');
    Route::post('/ipn',     'ipn')->name('sslcommerz.ipn');
});

// ── Middle East Payment Routes ──────────────────────────────────────────────
use App\Http\Controllers\Api\MiddleEastPaymentController;

Route::prefix('payment')->group(function () {

    // Resolve payment methods — public (used before login for UX)
    Route::post('/resolve-methods', [MiddleEastPaymentController::class, 'resolveMethods']);

    // VAT calculator — public
    Route::post('/vat/calculate',   [MiddleEastPaymentController::class, 'calculateVAT']);
    Route::post('/split-calculate', [MiddleEastPaymentController::class, 'calculateSplit']);

    // BNPL eligibility check
    Route::post('/bnpl/check', [MiddleEastPaymentController::class, 'checkBNPL']);

    // Authenticated + tenant-identified routes
    Route::middleware([IdentifyTenant::class, AuthenticateToken::class])->group(function () {

        // Unified charge endpoint (MADA/STC Pay/Tabby/Tamara/Postpay/COD)
        Route::post('/charge', [MiddleEastPaymentController::class, 'charge']);

        // Unified refund
        Route::post('/refund', [MiddleEastPaymentController::class, 'refund']);

        // COD operations
        Route::prefix('cod')->group(function () {
            Route::post('/create',                       [MiddleEastPaymentController::class, 'createCOD']);
            Route::post('/{orderId}/verify-otp',         [MiddleEastPaymentController::class, 'verifyCODOtp']);
            Route::post('/{orderId}/confirm-delivery',   [MiddleEastPaymentController::class, 'confirmCODDelivery']);
        });
    });
});

// Invoice download (authenticated)
Route::middleware([IdentifyTenant::class, AuthenticateToken::class])
    ->get('/invoices/{id}/download', [MiddleEastPaymentController::class, 'downloadInvoice']);

// ── BNPL Provider Callbacks (no auth — redirects from Tabby/Tamara/Postpay) ──
Route::prefix('payment/bnpl')->group(function () {
    Route::get('/tabby/success',    fn() => response()->json(['status' => 'tabby_success']))->name('tabby.success');
    Route::get('/tabby/cancel',     fn() => response()->json(['status' => 'tabby_cancel']))->name('tabby.cancel');
    Route::get('/tabby/failure',    fn() => response()->json(['status' => 'tabby_failure']))->name('tabby.failure');
    Route::get('/tamara/success',   fn() => response()->json(['status' => 'tamara_success']))->name('tamara.success');
    Route::get('/tamara/failure',   fn() => response()->json(['status' => 'tamara_failure']))->name('tamara.failure');
    Route::get('/tamara/cancel',    fn() => response()->json(['status' => 'tamara_cancel']))->name('tamara.cancel');
    Route::post('/tamara/notify',   fn() => response()->json(['ok' => true]))->name('tamara.notify');
    Route::get('/postpay/success',  fn() => response()->json(['status' => 'postpay_success']))->name('postpay.success');
    Route::get('/postpay/cancel',   fn() => response()->json(['status' => 'postpay_cancel']))->name('postpay.cancel');
});

// Moyasar card payment callback (3DS redirect + webhook)
Route::post('/moyasar/webhook',  fn() => response()->json(['received' => true]))->name('moyasar.callback');
Route::get('/stcpay/callback',   fn() => response()->json(['received' => true]))->name('stcpay.callback');

