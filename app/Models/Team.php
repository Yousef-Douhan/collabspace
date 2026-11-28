<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A group of people with one or more leads; linking it to a project adds its members there.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, User> $leads
 * @property-read Collection<int, Project> $projects
 * @property-read User|null $creator
 * @property-read int|null $members_count
 * @property-read int|null $projects_count
 * @property-read Membership $pivot the membership row, when loaded through a many-to-many relation
 */
class Team extends Model
{
    protected $fillable = ['name', 'description', 'created_by'];

    /** @return BelongsToMany<User, $this, Membership> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(Membership::class)->withPivot('role')->withTimestamps()->orderBy('name');
    }

    /** @return BelongsToMany<User, $this, Membership> */
    public function leads(): BelongsToMany
    {
        return $this->members()->wherePivot('role', TeamRole::Lead->value);
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLedBy(User $user): bool
    {
        return $this->leads()->whereKey($user->id)->exists();
    }

    /** One of a few soft colours, stable per team, for its badge. */
    public function colorClasses(): string
    {
        $palette = ['bg-violet-100 text-violet-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700'];

        return $palette[$this->id % count($palette)];
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
    }
}
