<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Product\Controllers\ProductController;
use Modules\Product\Controllers\SuggestProductCandidatesController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {
        // MUST be registered before the apiResource below, otherwise
        // GET /products/{product} (show) swallows /products/suggest,
        // resolving "suggest" as a hash_id.
        Route::get('products/suggest', SuggestProductCandidatesController::class);
        Route::apiResource('products', ProductController::class);
    });
