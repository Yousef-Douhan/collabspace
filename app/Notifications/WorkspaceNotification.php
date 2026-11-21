<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base for in-app notifications stored in the `notifications` table.
 * Each subclass only describes its type, title, message and related ids.
 */
abstract class WorkspaceNotification extends Notification
{
    abstract public function type(): string;

    abstract public function title(): string;

    abstract public function message(): string;

    /** @return array<string, mixed> ids the client can use to open the related item */
    public function context(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'message' => $this->message(),
            ...$this->context(),
        ];
    }
}
