<?php

use Illuminate\Support\Facades\Route;
use App\Modules\FlashSales\Controllers\Api\FlashSaleController;

Route::prefix('flash-sales')->group(function () {
    Route::get('/', [FlashSaleController::class, 'index']);
    Route::post('/', [FlashSaleController::class, 'store']);
    Route::get('/active', [FlashSaleController::class, 'active']);
    Route::delete('/{id}', [FlashSaleController::class, 'destroy']);
});
