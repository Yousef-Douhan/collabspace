<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;

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

    /** Also e-mailed when the organizer ticked "Email" in the New Meeting form. */
    public function via(object $notifiable): array
    {
        return $this->meeting->notifies('email') ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().': '.$this->meeting->title)
            ->greeting($this->title())
            ->line($this->message())
            ->when($this->meeting->meeting_link, fn (MailMessage $m) => $m->line('Join link: '.$this->meeting->meeting_link))
            ->action('Open meeting', route('meetings.show', $this->meeting));
    }
}
