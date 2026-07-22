<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Merchant\Controllers\MerchantController;
use Modules\Merchant\Controllers\SuggestMerchantCandidatesController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {
        // MUST be registered before the apiResource below, otherwise
        // GET /merchants/{merchant} (show) swallows /merchants/suggest,
        // resolving "suggest" as a hash_id.
        Route::get('merchants/suggest', SuggestMerchantCandidatesController::class);
        Route::apiResource('merchants', MerchantController::class);
    });
