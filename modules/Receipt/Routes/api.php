<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Receipt\Controllers\ReceiptController;
use Modules\Receipt\Controllers\ReviewReceiptController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {
        Route::get('receipts', [ReceiptController::class, 'index']);
        Route::post('receipts', [ReceiptController::class, 'store']);
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show']);
        Route::post('receipts/{receipt}/review', ReviewReceiptController::class);
    });
