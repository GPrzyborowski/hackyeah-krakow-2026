<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1,api-register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('login');

    Route::middleware(['auth:sanctum', 'throttle:120,1,api-user'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1,api-verification-notification')
            ->name('verification.send');
    });
});
