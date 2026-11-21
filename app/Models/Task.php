<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\Track;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'description',
        'status',
        'priority',
        'track',
        'progress',
        'position',
        'start_date',
        'due_date',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'todo',
        'priority' => 'medium',
        'track' => 'back_end',
        'progress' => 0,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'track' => Track::class,
            'progress' => 'integer',
            'position' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** Tasks in projects the user can see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $q) => $q->visibleTo($user));
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== TaskStatus::Completed
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    // --- Relations -------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_reviewer')
            ->withTimestamps();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }
}
