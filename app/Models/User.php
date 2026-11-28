<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\Track;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property string|null $job_title
 * @property Track $track
 * @property UserRole $role
 * @property Availability $availability
 * @property int $years_of_experience
 * @property string|null $about
 * @property string $locale
 * @property Carbon|null $joined_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deactivated_at
 * @property string|null $github_id
 * @property string|null $google_id
 * @property string|null $oauth_avatar
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Project> $ownedProjects
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Task> $createdTasks
 * @property-read Collection<int, ProjectFile> $uploadedFiles
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Collection<int, Meeting> $meetings
 * @property-read Collection<int, ActivityLog> $activityLogs
 * @property-read Collection<int, AiMessage> $aiMessages
 * @property-read int|null $tasks_count
 * @property-read int|null $completed_tasks_count
 * @property-read int|null $projects_count
 * @property-read int|null $open_tasks_count
 * @property-read Membership $pivot the membership row, when loaded through a many-to-many relation
 */
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'job_title',
        'track',
        'role',
        'availability',
        'years_of_experience',
        'about',
        'locale',
        'joined_at',
    ];

    /** Same defaults as the migration, so a freshly created model is complete without a reload. */
    protected $attributes = [
        'track' => 'back_end',
        'role' => 'member',
        'availability' => 'available',
        'years_of_experience' => 0,
        'locale' => 'en',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'github_id',
        'google_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'track' => Track::class,
            'role' => UserRole::class,
            'deactivated_at' => 'datetime',
            'availability' => Availability::class,
            'years_of_experience' => 'integer',
            'joined_at' => 'date',
        ];
    }

    /** E-mails (password reset, meeting invitations…) go out in the user's language. */
    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        // An uploaded picture wins; otherwise the GitHub / Google profile picture, if any.
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : $this->oauth_avatar;
    }

    // --- Relations -------------------------------------------------------

    /** @return BelongsToMany<Project, $this, Membership> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Team, $this, Membership> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->using(Membership::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<Project, $this> */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /** @return BelongsToMany<Task, $this, Membership> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->using(Membership::class)
            ->withPivot('is_reviewer')
            ->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /** @return HasMany<ProjectFile, $this> */
    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'uploaded_by');
    }

    /** @return BelongsToMany<Conversation, $this, Membership> */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->using(Membership::class)
            ->withPivot(['is_pinned', 'is_archived', 'last_read_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Meeting, $this, Membership> */
    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class)
            ->using(Membership::class)
            ->withPivot('response')
            ->withTimestamps();
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** @return HasMany<AiMessage, $this> */
    public function aiMessages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
