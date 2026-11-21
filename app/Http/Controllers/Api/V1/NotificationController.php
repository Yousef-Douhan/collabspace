<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    /** GET /notifications?unread=1&type=task_assigned */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = ($request->boolean('unread') ? $user->unreadNotifications() : $user->notifications())
            ->when($request->filled('type'), fn ($q) => $q->where('data->type', $request->input('type')))
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }

    public function markRead(Request $request, string $id): NotificationResource
    {
        // Scoped to the current user: someone else's id is simply "not found".
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function markAllRead(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
