<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Controllers\LoginController;
use Modules\Auth\Controllers\LogoutController;
use Modules\Auth\Controllers\RegisterController;

Route::middleware(['api', 'throttle:6,1'])
    ->prefix('api/auth')
    ->name('api.auth.')
    ->group(function () {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->name('login');
    });

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/auth')
    ->name('api.auth.')
    ->group(function () {
        Route::post('logout', LogoutController::class)->name('logout');
    });
