<?php

namespace App\Models;

use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    /** Meetings the user attends, created, or that belong to one of their projects. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('attendees', fn (Builder $a) => $a->whereKey($user->id))
                ->orWhereHas('project', fn (Builder $p) => $p->visibleTo($user));
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('response')
            ->withTimestamps();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class)->latest('id');
    }
}
