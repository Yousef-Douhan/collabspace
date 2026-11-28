<?php

namespace App\Models;

use App\Notifications\CommentAdded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * A comment on a task or a meeting.
 *
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $commentable
 * @property-read User $user
 */
class Comment extends Model
{
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
