<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\User;

class MeetingInvitation extends WorkspaceNotification
{
    public function __construct(public Meeting $meeting, public User $by) {}

    public function type(): string
    {
        return 'meeting_invitation';
    }

    public function title(): string
    {
        return 'Meeting invitation';
    }

    public function message(): string
    {
        return "{$this->by->name} invited you to \"{$this->meeting->title}\" on {$this->meeting->starts_at->format('M j, H:i')}.";
    }

    public function context(): array
    {
        return ['meeting_id' => $this->meeting->id];
    }
}
