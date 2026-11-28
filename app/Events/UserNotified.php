<?php

namespace App\Events;

use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notification was just stored for a user: their open pages ring the bell, add it to
 * the menu and show a toast (Reverb, private channel App.Models.User.{id}).
 */
class UserNotified implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @var array{id: string, title: string, message: string, url: string, time: string, unread: int} */
    public array $payload;

    public function __construct(public int $userId, DatabaseNotification $notification, User $user)
    {
        $text = WorkspaceNotification::present($notification->data, $user->locale);

        $this->payload = [
            'id' => $notification->id,
            'title' => $text['title'],
            'message' => $text['message'],
            'url' => route('notifications.open', $notification->id),
            'time' => __('just now', [], $user->locale),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("App.Models.User.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
