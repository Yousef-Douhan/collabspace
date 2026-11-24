<?php

namespace App\Models;

use App\Notifications\CommentAdded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** A comment on a task or a meeting. */
class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'body',
        'edited_at',
    ];

    /**
     * A new comment notifies the people involved, never its author:
     * task → its assignees and creator; meeting → its attendees and organiser.
     * Works for every entry point (web, API, seeder) because it lives on the model.
     */
    protected static function booted(): void
    {
        static::created(function (Comment $comment) {
            $subject = $comment->commentable;

            $people = match (true) {
                $subject instanceof Task => $subject->assignees()->get()->push($subject->creator),
                $subject instanceof Meeting => $subject->attendees()->get()->push($subject->creator),
                default => new Collection,
            };

            $recipients = $people->filter()
                ->unique('id')
                ->reject(fn (User $user) => $user->id === $comment->user_id || ! $user->isActive());

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new CommentAdded($comment->loadMissing('user', 'commentable')));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
