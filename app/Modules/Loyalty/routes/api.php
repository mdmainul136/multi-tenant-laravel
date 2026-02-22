<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Loyalty\Controllers\Api\LoyaltyController;

Route::prefix('loyalty')->group(function () {
    Route::get('/program', [LoyaltyController::class, 'getProgram']);
    Route::post('/program', [LoyaltyController::class, 'updateProgram']);
    Route::get('/points/{customerId}', [LoyaltyController::class, 'getCustomerPoints']);
    Route::get('/tiers', [LoyaltyController::class, 'getTiers']);
    Route::post('/tiers', [LoyaltyController::class, 'updateTier']);
});
