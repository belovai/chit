<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\User\Controllers\AccountController;
use Modules\User\Controllers\UserController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {
        Route::get('account', [AccountController::class, 'show'])->name('account.show');
        Route::patch('account', [AccountController::class, 'update'])->name('account.update');
        Route::put('account/password', [AccountController::class, 'updatePassword'])
            ->name('account.password.update');
        Route::delete('account', [AccountController::class, 'destroy'])->name('account.destroy');

        Route::apiResource('users', UserController::class);
    });
