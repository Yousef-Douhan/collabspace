<?php

namespace App\Models;

use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $project_id
 * @property int $created_by
 * @property string $title
 * @property string|null $agenda
 * @property string|null $location
 * @property string|null $meeting_link
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int|null $reminder_minutes
 * @property Carbon|null $reminder_sent_at
 * @property string|null $transcript
 * @property string|null $ai_summary
 * @property list<string>|null $key_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $notes
 * @property list<string>|null $notify_channels
 * @property-read Project|null $project
 * @property-read User $creator
 * @property-read Collection<int, User> $attendees
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, MeetingAttachment> $attachments
 * @property-read int|null $comments_count
 * @property-read Membership $pivot the membership row, when loaded through a many-to-many relation
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'agenda',
        'notes',
        'location',
        'meeting_link',
        'starts_at',
        'ends_at',
        'reminder_minutes',
        'reminder_sent_at',
        'notify_channels',
        'transcript',
        'ai_summary',
        'key_points',
    ];

    /** Channels chosen in the "New Meeting" form, besides the in-app notification. */
    public function notifies(string $channel): bool
    {
        return in_array($channel, $this->notify_channels ?? [], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'reminder_minutes' => 'integer',
            'key_points' => 'array',
            'notify_channels' => 'array',
        ];
    }

    /**
     * Meetings the user attends, created, or that belong to one of their projects.
     *
     * @param  Builder<Meeting>  $query
     * @return Builder<Meeting>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('attendees', fn (Builder $a) => $a->whereKey($user->id))
                ->orWhereIn('project_id', Project::visibleTo($user)->select('projects.id'));
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this, Membership> */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('response')
            ->withTimestamps();
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest('id');
    }

    /** @return HasMany<MeetingAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class)->latest('id');
    }
}
