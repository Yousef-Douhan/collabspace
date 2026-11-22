<?php

use App\Http\Controllers\Web\AiController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FileController;
use App\Http\Controllers\Web\MeetingController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Blade UI (session auth). Reuses the same Form Requests, Policies and Services as /api/v1.
*/

// Language switch (guests: session only; signed-in users: saved on the account too).
Route::get('locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

    $request->session()->put('locale', $locale);
    $request->user()?->update(['locale' => $locale]);

    return back();
})->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');

    // Projects
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::put('projects/{project}/wiki', [ProjectController::class, 'updateWiki'])->name('projects.wiki');
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember'])->name('projects.members.store');
    Route::delete('projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->name('projects.members.destroy');

    // Tasks (Kanban)
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');

    // Files
    Route::get('files', [FileController::class, 'index'])->name('files.index');
    Route::post('files', [FileController::class, 'store'])->name('files.store');
    Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::delete('files/{file}', [FileController::class, 'destroy'])->name('files.destroy');

    // Chats
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('chats/{conversation}', [ChatController::class, 'index'])->name('chats.show');
    Route::post('chats/{conversation}/messages', [ChatController::class, 'send'])->name('chats.send');
    Route::patch('chats/{conversation}/state', [ChatController::class, 'state'])->name('chats.state');
    Route::patch('messages/{message}', [ChatController::class, 'updateMessage'])->name('messages.update');
    Route::delete('messages/{message}', [ChatController::class, 'destroyMessage'])->name('messages.destroy');
    Route::get('messages/{message}/attachment', [ChatController::class, 'attachment'])->name('messages.attachment');

    // Meetings
    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::put('meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
    Route::post('meetings/{meeting}/respond', [MeetingController::class, 'respond'])->name('meetings.respond');
    Route::post('meetings/{meeting}/summarize', [MeetingController::class, 'summarize'])->name('meetings.summarize')->middleware('throttle:10,1');
    Route::put('meetings/{meeting}/notes', [MeetingController::class, 'updateNotes'])->name('meetings.notes');
    Route::post('meetings/{meeting}/comments', [MeetingController::class, 'comment'])->name('meetings.comments.store');
    Route::post('meetings/{meeting}/attachments', [MeetingController::class, 'storeAttachment'])->name('meetings.attachments.store');
    Route::get('meeting-attachments/{attachment}', [MeetingController::class, 'downloadAttachment'])->name('meetings.attachments.download');
    Route::delete('meeting-attachments/{attachment}', [MeetingController::class, 'destroyAttachment'])->name('meetings.attachments.destroy');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
    Route::delete('reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
    Route::get('reports/{report}/export', [\App\Http\Controllers\Api\V1\ReportController::class, 'export'])->name('reports.export');

    // Ask AI
    Route::get('ai', [AiController::class, 'index'])->name('ai.index');
    Route::post('ai', [AiController::class, 'ask'])->name('ai.ask')->middleware('throttle:20,1');
    Route::delete('ai', [AiController::class, 'clear'])->name('ai.clear');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Members & settings
    Route::get('members/{user}', [MemberController::class, 'show'])->name('members.show');
    Route::get('settings', [MemberController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [MemberController::class, 'update'])->name('settings.update');
    Route::post('settings/avatar', [MemberController::class, 'avatar'])->name('settings.avatar');
    Route::put('settings/password', [MemberController::class, 'password'])->name('settings.password');
});
