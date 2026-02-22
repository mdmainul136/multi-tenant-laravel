<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Finance\Controllers\FinanceController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class,
    'module.access:finance',
    'quota.enforce'
])->prefix('finance')->group(function () {
    
    // ── Chart of Accounts ──────────────────────────────────────────────
    Route::get('/accounts',           [FinanceController::class, 'getAccounts']);
    Route::post('/accounts',          [FinanceController::class, 'storeAccount']);
    Route::get('/accounts/{id}/ledger',[FinanceController::class, 'getAccountLedger']);

    // ── Transactions ───────────────────────────────────────────────────
    Route::post('/transactions',       [FinanceController::class, 'recordTransaction']);
    
    // ── Reports ────────────────────────────────────────────────────────
    Route::get('/reports/profit-loss', [FinanceController::class, 'getProfitLoss']);
    
});
