<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
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
