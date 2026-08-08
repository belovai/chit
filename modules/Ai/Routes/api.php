<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ai\Controllers\AiCredentialController;
use Modules\Ai\Controllers\AiProviderController;
use Modules\Ai\Controllers\AiUsageController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {
        Route::get('ai/providers', [AiProviderController::class, 'index'])->name('ai.providers.index');
        Route::get('ai/usage', [AiUsageController::class, 'index'])->name('ai.usage.index');

        Route::get('ai/credentials', [AiCredentialController::class, 'index'])->name('ai.credentials.index');
        Route::post('ai/credentials', [AiCredentialController::class, 'store'])->name('ai.credentials.store');

        Route::middleware('can:update,credential')->group(function () {
            Route::patch('ai/credentials/{credential}', [AiCredentialController::class, 'update'])
                ->name('ai.credentials.update');
            Route::post('ai/credentials/{credential}/activate', [AiCredentialController::class, 'activate'])
                ->name('ai.credentials.activate');
            Route::post('ai/credentials/{credential}/verify', [AiCredentialController::class, 'verify'])
                ->name('ai.credentials.verify');
        });

        Route::delete('ai/credentials/{credential}', [AiCredentialController::class, 'destroy'])
            ->middleware('can:delete,credential')
            ->name('ai.credentials.destroy');
    });
