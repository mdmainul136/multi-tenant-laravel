<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Marketing\Controllers\MarketingController;

Route::middleware(['auth:sanctum', 'module.access:marketing', 'identify.tenant'])->prefix('marketing')->group(function () {
    Route::get('/campaigns',           [MarketingController::class, 'index']);
    Route::post('/campaigns',          [MarketingController::class, 'store']);
    Route::get('/campaigns/{id}',      [MarketingController::class, 'show']);
    Route::post('/campaigns/{id}/send', [MarketingController::class, 'send']);
    Route::get('/campaigns/{id}/analytics', [MarketingController::class, 'analytics']);
    
    // Additional routes for Templates and Audiences
});
