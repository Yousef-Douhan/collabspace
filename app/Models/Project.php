<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** Projects the user is a member of (admins see everything). */
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
        $role = $this->members()->whereKey($user->id)->first()?->pivot?->role;

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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
