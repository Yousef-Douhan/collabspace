<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ConversationType $type
 * @property string|null $name
 * @property int|null $project_id
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $participants
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $latestMessage
 * @property-read Project|null $project
 * @property-read User $creator
 * @property-read int|null $unread_count
 * @property-read Membership|null $pivot the viewer's membership row, when loaded through $user->conversations
 */
class Conversation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'project_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
        ];
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /** Channel name, or the other person's name for a direct chat. */
    public function displayNameFor(User $viewer): string
    {
        if ($this->type === ConversationType::Channel) {
            return (string) $this->name;
        }

        return $this->participants->firstWhere('id', '!=', $viewer->id)->name ?? (string) $this->name;
    }

    /** @return BelongsToMany<User, $this, Membership> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot(['is_pinned', 'is_archived', 'last_read_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasOne<Message, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
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
}
