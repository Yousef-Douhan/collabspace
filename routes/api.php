<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| CollabSpace REST API — v1
| Base URL: /api/v1   ·   Auth: Authorization: Bearer <token>
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // --- Auth (public) ----------------------------------------------------
    Route::prefix('auth')->name('auth.')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    // --- Authenticated ----------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
        Route::post('me/avatar', [ProfileController::class, 'updateAvatar'])->name('me.avatar');
        Route::put('me/password', [ProfileController::class, 'updatePassword'])->name('me.password');
    });
});
