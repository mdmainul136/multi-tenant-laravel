<?php

use Illuminate\Support\Facades\Route;
use App\Modules\CrossBorderIOR\Controllers\DashboardController;
use App\Modules\CrossBorderIOR\Controllers\ForeignOrderController;
use App\Modules\CrossBorderIOR\Controllers\ProductScraperController;
use App\Modules\CrossBorderIOR\Controllers\PaymentController;
use App\Modules\CrossBorderIOR\Controllers\StripeController;
use App\Modules\CrossBorderIOR\Controllers\NagadController;
use App\Modules\CrossBorderIOR\Controllers\CourierController;
use App\Modules\CrossBorderIOR\Controllers\SettingsController;
use App\Modules\CrossBorderIOR\Controllers\InvoiceController;
use App\Modules\CrossBorderIOR\Controllers\AiContentController;
use App\Modules\CrossBorderIOR\Controllers\BillingController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\IdentifyTenantByUrl;
use App\Modules\CrossBorderIOR\Controllers\AdminApprovalController;

// ---------------------------------------------------------------------------
// PUBLIC: Payment callbacks (no auth -- browser redirects / gateway POSTs)
// ---------------------------------------------------------------------------
Route::prefix('ior/payment')->group(function () {
    // bKash callback (browser redirect after customer pays)
    Route::get('/bkash/callback',  [PaymentController::class, 'bkashCallback'])->name('ior.bkash.callback');

    // SSLCommerz callbacks
    Route::post('/sslcommerz/ipn',    [PaymentController::class, 'sslcommerzIpn'])->name('ior.sslcommerz.ipn');
    Route::post('/sslcommerz/success', fn() => response()->json(['status' => 'success']))->name('ior.sslcommerz.success');
    Route::get('/sslcommerz/success',  fn() => redirect()->away(env('FRONTEND_URL', '/') . '?ior_payment=success'));
    Route::post('/sslcommerz/fail',    fn() => response()->json(['status' => 'fail']))->name('ior.sslcommerz.fail');
    Route::post('/sslcommerz/cancel',  fn() => response()->json(['status' => 'cancel']))->name('ior.sslcommerz.cancel');

    // Stripe -- webhook (must receive raw body for HMAC verification)
    Route::post('/callback/{gateway}', [BillingController::class, 'callback'])->withoutMiddleware([IdentifyTenantByUrl::class]);
    Route::post('/stripe/webhook', [StripeController::class, 'webhook'])->name('ior.stripe.webhook');

    // Stripe â€” browser redirects from Checkout page
    Route::get('/stripe/success', [StripeController::class, 'success'])->name('ior.stripe.success');
    Route::get('/stripe/cancel',  [StripeController::class, 'cancel'])->name('ior.stripe.cancel');

    // Nagad â€” callback (browser redirect, no auth)
    Route::get('/nagad/callback', [NagadController::class, 'callback'])->name('ior.nagad.callback');

    // Public tracking (no user auth, but mapped to tenant)
    Route::middleware([IdentifyTenant::class, 'module.access:ior'])->prefix('courier')->group(function () {
        Route::get('available', [CourierController::class, 'availableCouriers']);
        Route::get('/track', [CourierController::class, 'track']);
        Route::post('/detect', [CourierController::class, 'detectCourier']);
    });

    // Public Webhooks for Couriers (Tenant identified from URL)
    Route::middleware(['tenant.url', 'module.access:ior'])
        ->prefix('webhooks/{tenantId}')
        ->group(function () {
            Route::post('/pathao',    [\App\Modules\CrossBorderIOR\Controllers\CourierWebhookController::class, 'pathao']);
            Route::post('/steadfast', [\App\Modules\CrossBorderIOR\Controllers\CourierWebhookController::class, 'steadfast']);
            Route::post('/redx',      [\App\Modules\CrossBorderIOR\Controllers\CourierWebhookController::class, 'redx']);
        });
});

// ---------------------------------------------------------------------------
// AUTHENTICATED ROUTES (tenant identified + user logged in)
// ---------------------------------------------------------------------------
Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class,
    'module.access:ior',
    'quota.enforce'
])->prefix('ior')->group(function () {

    // ==========================================
    // DASHBOARD
    // ==========================================
    Route::get('/dashboard',             [DashboardController::class, 'stats']);
    Route::get('/dashboard/performance', [DashboardController::class, 'performanceMetrics']);

    // ==========================================
    // PRODUCT SCRAPER & PRICE QUOTE
    // ==========================================
    Route::post('/scrape',   [ProductScraperController::class, 'scrape']);  // URL -> product + BDT price
    Route::post('/quote',    [ProductScraperController::class, 'quote']);   // Manual USD -> BDT quote
    Route::post('/simulate-cost', [ProductScraperController::class, 'simulateLandedCost']); // IOR Tax + Shipping simulation
    Route::post('/seed-hs-codes', function() {
        app(\App\Modules\CrossBorderIOR\Services\CustomsDutyService::class)->seedCommonCodes();
        return response()->json(['success' => true, 'message' => 'HS Codes seeded']);
    });
    Route::get('/catalog',   [ProductScraperController::class, 'catalog']); // browse imported catalog

    // ==========================================
    // HS CODE LOOKUP (Credit Based)
    // ==========================================
    Route::prefix('hs')->controller(\App\Modules\CrossBorderIOR\Controllers\HsCodeController::class)->group(function () {
        Route::get('/search',    'search'); // Free search
        Route::post('/select',   'select'); // Paid selection
    });

    // ==========================================
    // FOREIGN ORDERS (IOR Core)
    // ==========================================
    Route::prefix('orders')->controller(ForeignOrderController::class)->group(function () {
        Route::get('/',                 'index');           // list + filter + paginate
        Route::post('/',                'store');           // place order (calc pricing)
        Route::get('/{id}',             'show');            // order detail
        Route::get('/{id}/proforma',    'proforma');        // proforma data
        Route::put('/{id}',             'update');          // admin edit
        Route::put('/{id}/status',      'updateStatus');    // status lifecycle
        Route::delete('/{id}',          'destroy');
    });

 // INVOICES
   Route::prefix('invoices')->controller(InvoiceController::class)->group(function () {
        Route::get('/{id}',          'show');      // JSON with HTML
        Route::get('/{id}/download', 'download');  // Raw HTML for print
    });

  // PAYMENT (initiate)
  Route::prefix('payment')->group(function () {
        // Nagad
        Route::post('/nagad/initiate',     [NagadController::class, 'initiate']);
        Route::get('/nagad/status/{id}',   [NagadController::class, 'status']);

        // bKash
        Route::post('/bkash/initiate',      [PaymentController::class, 'bkashInitiate']);

        // SSLCommerz
        Route::post('/sslcommerz/initiate', [PaymentController::class, 'sslcommerzInitiate']);

        // Stripe Checkout Session
        Route::post('/stripe/initiate',     [StripeController::class,  'initiate']);

        // Unified payment status for any gateway
        Route::get('/status/{orderId}',     [PaymentController::class, 'status']);
    });

   // COURIER -- tracking & assignment

    Route::prefix('courier')->controller(CourierController::class)->group(function () {
        Route::get('/track',            'track');         // ?number=1Z...&courier=ups
        Route::get('/track-order/{id}', 'trackOrder');   // track by IOR order ID
        Route::post('/detect',          'detectCourier');// detect courier from number
        Route::post('/assign',          'assign');        // attach tracking # to order
        Route::post('/book',            'book');          // auto-create parcel (Pathao/Steadfast/RedX/FedEx/DHL)
        Route::get('/rates',            'rates');         // configured shipping rates
    });

    // ==========================================
    // WAREHOUSE — Inbound & Outbound
    // ==========================================
    Route::prefix('warehouse')->controller(\App\Modules\CrossBorderIOR\Controllers\IorWarehouseController::class)->group(function () {
        Route::post('/receive',         'receive');        // Inbound: mark arrived
        Route::post('/dispatch',        'dispatch');       // Outbound: single dispatch
        Route::post('/batch-dispatch',  'batchDispatch');  // Outbound: batch dispatch
        Route::post('/customs-clear',   'customsClear');   // Mark customs cleared
        Route::post('/deliver',         'deliver');        // Mark delivered
    });

    // ==========================================
    // SHIPMENT BATCHES
    // ==========================================
    Route::prefix('shipment-batches')->controller(\App\Modules\CrossBorderIOR\Controllers\IorWarehouseController::class)->group(function () {
        Route::get('/',     'listBatches');     // List all batches
        Route::get('/{id}', 'batchDetail');     // Batch detail with orders
    });

    // ==========================================
    // SETTINGS & CONFIG
    // ==========================================
    Route::prefix('settings')->controller(SettingsController::class)->group(function () {
        Route::get('/',                         'index');
        Route::put('/',                         'update');

        Route::get('/customs-rates',            'customsRates');
        Route::put('/customs-rates/{id}',       'updateCustomsRate');

        Route::get('/exchange-rate',            'exchangeRate');
        Route::post('/exchange-rate/refresh',   'refreshExchangeRate');

        Route::get('/shipping-rates',           'shippingRates');
        Route::put('/shipping-rates/{id}',      'updateShippingRate');

        Route::get('/couriers',                 'couriers');
        Route::put('/couriers/{code}',          'updateCourier');
    });

    // ---------------------------------------------------------------------------
    // SCRAPER: Product Data Extraction
    // ---------------------------------------------------------------------------
    Route::group(['prefix' => 'scraper', 'middleware' => ['advanced_quota']], function () {
        Route::post('/scrape',                 [ProductScraperController::class, 'scrape']); // apify/oxylabs fetch
        Route::post('/bulk-import',            [ProductScraperController::class, 'bulkImport']);
    });

    // ---------------------------------------------------------------------------
    // AI CONTENT: Descriptions, SEO, Analysis
    // ---------------------------------------------------------------------------
    Route::prefix('ai')->middleware(['advanced_quota'])->controller(AiContentController::class)->group(function () {
        Route::get('/',                        'status');           // which provider is active
        Route::put('/settings',                'updateSettings');   // set API keys + model
        Route::post('/generate-description',   'generateDescription');
        Route::post('/optimize-seo',           'optimizeSeo');
        Route::post('/analyze-image',          'analyzeImage');
        Route::post('/listing',                'listing');          // full marketplace listing
        Route::post('/translate',              'translate');        // text -> Bangla
        Route::post('/social',                 'social');           // product -> FB/IG caption
        Route::post('/enrich-order/{id}',      'enrichOrder');      // generate + save to order
        Route::get('/bestsellers',             'bestsellers');      // Apify/Oxylabs bestseller fetch
    });

    // ==========================================
    // BILLING & WALLET (Authenticated)
    // ==========================================
    Route::prefix('billing')->group(function () {
        Route::get('/stats', [BillingController::class, 'index']);
        Route::get('/transactions', [BillingController::class, 'index']); 
        Route::post('/topup', [BillingController::class, 'initiateTopup']);
    });

    // ==========================================
    // ADMIN: Data Sync & Price Management
    // ==========================================
    Route::prefix('admin')->group(function () {
        // Sync foreign product prices from source marketplaces (Oxylabs/Apify)
        Route::post('/sync-products', function (\Illuminate\Http\Request $request) {
            $service  = app(\App\Modules\CrossBorderIOR\Services\ForeignProductSyncService::class);
            $ids      = $request->input('product_ids');
            $provider = $request->input('provider', 'oxylabs');
            return response()->json($service->sync($ids, $provider));
        });

        // Bulk recalculate IOR order prices based on new exchange rate
        Route::post('/recalculate-prices', function (\Illuminate\Http\Request $request) {
            $service = app(\App\Modules\CrossBorderIOR\Services\BulkPriceRecalculatorService::class);
            return response()->json($service->recalculate(
                orderIds:    $request->input('order_ids'),
                force:       (bool) $request->input('force', false),
                triggeredBy: 'api',
            ));
        });

        // Bulk import foreign products from URL list (max 10 per request)
        Route::post('/bulk-import', [ProductScraperController::class, 'bulkImport']);
        
        // Sourcing Dashboard & Schema (Apify-style)
        Route::get('/sourcing/dashboard', [\App\Modules\CrossBorderIOR\Controllers\IorScraperDashboardController::class, 'index']);
        Route::post('/sourcing/debug', [\App\Modules\CrossBorderIOR\Controllers\IorScraperDashboardController::class, 'debugScrape']);
        Route::post('/sourcing/purchase-proxy', [\App\Modules\CrossBorderIOR\Controllers\IorScraperDashboardController::class, 'purchaseProxy']);
        Route::post('/sourcing/purchase-proxy', [\App\Modules\CrossBorderIOR\Controllers\IorScraperDashboardController::class, 'purchaseProxy']);
        Route::patch('/sourcing/settings/proxy', [\App\Modules\CrossBorderIOR\Controllers\IorScraperDashboardController::class, 'updateProxySettings']);

        // Bulk Margin Recalculation (Retailer Model)
        Route::post('/sourcing/recalculate-margins', function (\Illuminate\Http\Request $request) {
            $service = app(\App\Modules\CrossBorderIOR\Services\WarehouseMarginCalculator::class);
            return response()->json($service->recalculateAll($request->input('product_ids')));
        });

        // Sourcing Hardening / Approval Workflow
        Route::get('/sourcing/pending', [AdminApprovalController::class, 'pendingList']);
        Route::post('/sourcing/rewrite/{id}', [AdminApprovalController::class, 'rewrite']);
        Route::post('/sourcing/approve/{id}', [AdminApprovalController::class, 'approve']);
        Route::post('/sourcing/verify-warehouse/{id}', [AdminApprovalController::class, 'verifyWarehouse']);
        Route::post('/sourcing/block-sku/{id}', [AdminApprovalController::class, 'blockSku']);
        Route::get('/sourcing/blocked-domains', [AdminApprovalController::class, 'blockedDomains']);
        Route::post('/sourcing/block-domain', [AdminApprovalController::class, 'blockDomain']);

        // Warehouse Operations
        Route::post('/warehouse/receive', [\App\Modules\CrossBorderIOR\Controllers\IorWarehouseController::class, 'receive']);
    });
});

Route::prefix('ior/global')->group(function () {
    Route::get('/restricted-items', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'listRestrictedItems']);
    Route::post('/restricted-items', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'addRestrictedItem']);
    Route::delete('/restricted-items/{id}', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'deleteRestrictedItem']);

    Route::get('/countries', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'listCountries']);
    Route::post('/countries/{id}', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'updateCountry']); // Changed from PATCH to POST
    
    // Courier Management
    Route::get('/couriers', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'listCouriers']);
    Route::post('/couriers', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'addCourier']);
    Route::post('/couriers/{id}', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'updateCourier']);
    Route::delete('/couriers/{id}', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'deleteCourier']);

    // HS Code Global Management
    Route::get('/hs-lookups', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'listHsLookupLogs']);
    Route::post('/hs-lookup-cost', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'updateHsLookupCost']);
    // HS Code Lookup (Paid)
    Route::get('/hs/search', [\App\Modules\CrossBorderIOR\Controllers\HsCodeController::class, 'search']);
    Route::post('/hs/select', [\App\Modules\CrossBorderIOR\Controllers\HsCodeController::class, 'select']);
    Route::post('/hs/infer', [\App\Modules\CrossBorderIOR\Controllers\HsCodeController::class, 'infer']);
    Route::get('/hs/history', [\App\Modules\CrossBorderIOR\Controllers\HsCodeController::class, 'history']);
    Route::get('/hs-codes', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'hsCodes']);
    Route::post('/hs-codes', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'storeHsCode']);
    Route::put('/hs-codes/{id}', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'updateHsCode']);
    Route::delete('/hs-codes/{id}', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'destroyHsCode']);
    Route::get('/hs-stats', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'hsLookupStats']);

    Route::post('/seed-policies', [\App\Modules\CrossBorderIOR\Controllers\LandlordIorAdminController::class, 'seedGlobalPolicies']);
});
