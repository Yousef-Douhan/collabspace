<?php

namespace App\Notifications;

use App\Models\Meeting;

class MeetingReminder extends WorkspaceNotification
{
    public function __construct(public Meeting $meeting) {}

    public function type(): string
    {
        return 'meeting_reminder';
    }

    public function title(): string
    {
        return 'Meeting starting soon';
    }

    public function message(): string
    {
        return "\"{$this->meeting->title}\" starts at {$this->meeting->starts_at->format('H:i')}"
            .($this->meeting->location ? " in {$this->meeting->location}" : '').'.';
    }

    public function context(): array
    {
        return ['meeting_id' => $this->meeting->id, 'meeting_link' => $this->meeting->meeting_link];
    }
}
