<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
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
        Route::get('tasks/{task}/comments', [CommentController::class, 'taskIndex'])->name('tasks.comments.index');
        Route::post('tasks/{task}/comments', [CommentController::class, 'taskStore'])->name('tasks.comments.store');

        // --- Comments (tasks & meetings) --------------------------------
        Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        // --- Files (private storage, authorized download) ----------------
        Route::get('files', [FileController::class, 'index'])->name('files.index');
        Route::post('files', [FileController::class, 'store'])->name('files.store');
        Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
        Route::delete('files/{file}', [FileController::class, 'destroy'])->name('files.destroy');

        // --- Chats ---------------------------------------------------------
        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
        Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages.index');
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'send'])->name('conversations.messages.store');
        Route::post('conversations/{conversation}/read', [ConversationController::class, 'read'])->name('conversations.read');
        Route::get('messages/{message}/attachment', [ConversationController::class, 'attachment'])->name('messages.attachment');
        Route::patch('messages/{message}', [ConversationController::class, 'updateMessage'])->name('messages.update');
        Route::delete('messages/{message}', [ConversationController::class, 'destroyMessage'])->name('messages.destroy');

        // --- Meetings ------------------------------------------------------
        Route::apiResource('meetings', MeetingController::class);
        Route::post('meetings/{meeting}/respond', [MeetingController::class, 'respond'])->name('meetings.respond');
        Route::put('meetings/{meeting}/notes', [MeetingController::class, 'updateNotes'])->name('meetings.notes');
        Route::get('meetings/{meeting}/comments', [CommentController::class, 'meetingIndex'])->name('meetings.comments.index');
        Route::post('meetings/{meeting}/comments', [CommentController::class, 'meetingStore'])->name('meetings.comments.store');
        Route::post('meetings/{meeting}/attachments', [MeetingController::class, 'storeAttachment'])->name('meetings.attachments.store');
        Route::get('meeting-attachments/{attachment}/download', [MeetingController::class, 'downloadAttachment'])->name('meeting-attachments.download');
        Route::delete('meeting-attachments/{attachment}', [MeetingController::class, 'destroyAttachment'])->name('meeting-attachments.destroy');
        Route::post('meetings/{meeting}/summary', [MeetingController::class, 'summarize'])
            ->middleware('throttle:10,1')
            ->name('meetings.summary');

        // --- Notifications -------------------------------------------------
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // --- Ask AI --------------------------------------------------------
        Route::post('ai/ask', [AiController::class, 'ask'])->middleware('throttle:20,1')->name('ai.ask');
        Route::get('ai/messages', [AiController::class, 'history'])->name('ai.history');
        Route::delete('ai/messages', [AiController::class, 'clear'])->name('ai.clear');

        // --- Dashboard, reports, search, meta -----------------------------
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
        Route::apiResource('reports', ReportController::class)->except(['update']);
        Route::get('search', SearchController::class)->name('search');
        Route::get('meta', MetaController::class)->name('meta');
    });
});
