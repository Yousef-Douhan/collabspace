<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property Priority $priority
 * @property ProjectStatus $status
 * @property string|null $wiki
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, ProjectFile> $files
 * @property-read Collection<int, Meeting> $meetings
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Collection<int, ActivityLog> $activityLogs
 * @property-read int|null $members_count
 * @property-read int|null $tasks_count
 * @property-read int|null $completed_tasks_count
 * @property-read int|null $open_tasks_count
 * @property-read int|null $files_count
 * @property-read Membership $pivot the membership row, when loaded through a many-to-many relation
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'type',
        'priority',
        'status',
        'wiki',
        'start_date',
        'end_date',
    ];

    protected $attributes = [
        'type' => 'saas',
        'priority' => 'medium',
        'status' => 'active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Projects the user is a member of (admins see everything).
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $q) => $q->whereKey($user->id));
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function roleOf(User $user): ?ProjectRole
    {
        $role = $this->members()->whereKey($user->id)->first()?->pivot->role;

        return $role ? ProjectRole::from($role) : null;
    }

    /** Owner / manager of the project, or a workspace admin. */
    public function isManagedBy(User $user): bool
    {
        return $user->isAdmin()
            || in_array($this->roleOf($user), [ProjectRole::Owner, ProjectRole::Manager], true);
    }

    /**
     * 0–100. Uses tasks_count / completed_tasks_count when loaded with withCount(),
     * otherwise counts on demand.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $total = $this->tasks_count ?? $this->tasks()->count();
        $done = $this->completed_tasks_count
            ?? $this->tasks()->where('status', TaskStatus::Completed)->count();

        return $total > 0 ? (int) round($done / $total * 100) : 0;
    }

    // --- Relations -------------------------------------------------------

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this, Membership> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<ProjectFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /** @return HasMany<Meeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
