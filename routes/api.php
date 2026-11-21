<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserController;
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

        // --- Members directory -------------------------------------------
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

        // --- Projects ------------------------------------------------------
        Route::apiResource('projects', ProjectController::class);
        Route::put('projects/{project}/wiki', [ProjectController::class, 'updateWiki'])->name('projects.wiki');
        Route::get('projects/{project}/members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
        Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
        Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');

        // --- Tasks (Kanban) ----------------------------------------------
        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');

        // --- Files (private storage, authorized download) ----------------
        Route::get('files', [FileController::class, 'index'])->name('files.index');
        Route::post('files', [FileController::class, 'store'])->name('files.store');
        Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
        Route::delete('files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
    });
});
