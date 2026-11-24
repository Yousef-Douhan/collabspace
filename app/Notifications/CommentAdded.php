<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Meeting;
use App\Models\Task;
use Illuminate\Support\Str;

/** A new comment on a task you work on, or a meeting you attend / organise. */
class CommentAdded extends WorkspaceNotification
{
    public function __construct(public Comment $comment) {}

    public function type(): string
    {
        return 'comment_added';
    }

    public function title(): string
    {
        return 'New comment';
    }

    public function line(): array
    {
        $subject = $this->comment->commentable;

        return [':name commented on ":subject": ":text"', [
            'name' => $this->comment->user->name,
            'subject' => $subject instanceof Task || $subject instanceof Meeting ? $subject->title : '',
            'text' => Str::limit($this->comment->body, 80),
        ]];
    }

    public function context(): array
    {
        $subject = $this->comment->commentable;

        return match (true) {
            $subject instanceof Task => ['task_id' => $subject->id, 'project_id' => $subject->project_id],
            $subject instanceof Meeting => ['meeting_id' => $subject->id],
            default => [],
        };
    }
}
