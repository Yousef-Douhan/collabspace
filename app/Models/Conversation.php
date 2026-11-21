<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'project_id',
        'created_by',
    ];

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

        return $this->participants->firstWhere('id', '!=', $viewer->id)?->name ?? (string) $this->name;
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_pinned', 'is_archived', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
