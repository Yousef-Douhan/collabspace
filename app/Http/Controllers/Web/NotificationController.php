<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Marks one as read and follows its link (if any). */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $data = $notification->data;

        return redirect(match (true) {
            isset($data['conversation_id']) => route('chats.show', $data['conversation_id']),
            isset($data['task_id']) => route('tasks.show', $data['task_id']),
            isset($data['meeting_id']) => route('meetings.show', $data['meeting_id']),
            isset($data['project_id']) => route('projects.show', $data['project_id']),
            default => route('notifications.index'),
        });
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
