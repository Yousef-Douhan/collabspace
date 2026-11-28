<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\Track;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $created_by
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property Priority $priority
 * @property Track $track
 * @property int $progress
 * @property int $position
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $estimated_hours
 * @property-read Project $project
 * @property-read User $creator
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, ProjectFile> $files
 * @property-read Collection<int, Comment> $comments
 * @property-read int|null $files_count
 * @property-read int|null $comments_count
 * @property-read Membership $pivot the membership row, when loaded through a many-to-many relation
 */
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
        'estimated_hours',
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'track' => Track::class,
            'progress' => 'integer',
            'estimated_hours' => 'integer',
            'position' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Tasks in projects the user can see.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('project_id', Project::visibleTo($user)->select('projects.id'));
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== TaskStatus::Completed
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    // --- Relations -------------------------------------------------------

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
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('is_reviewer')
            ->withTimestamps();
    }

    /** @return HasMany<ProjectFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest('id');
    }
}
