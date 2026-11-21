<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\Track;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'track' => Track::class,
            'role' => UserRole::class,
            'availability' => Availability::class,
            'years_of_experience' => 'integer',
            'joined_at' => 'date',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    // --- Relations -------------------------------------------------------

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('is_reviewer')
            ->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'uploaded_by');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot(['is_pinned', 'is_archived', 'last_read_at'])
            ->withTimestamps();
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class)
            ->withPivot('response')
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function aiMessages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
