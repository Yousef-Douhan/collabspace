<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array{name: string, members?: list<int>}  $data
     */
    public function create(User $owner, array $data): Project
    {
        return DB::transaction(function () use ($owner, $data) {
            $project = Project::create([...Arr::except($data, ['members']), 'owner_id' => $owner->id]);

            $members = collect($data['members'] ?? [])
                ->reject(fn ($id) => (int) $id === $owner->id)
                ->mapWithKeys(fn ($id) => [(int) $id => ['role' => ProjectRole::Member->value]]);

            $project->members()->attach([
                $owner->id => ['role' => ProjectRole::Owner->value],
                ...$members->all(),
            ]);

            $this->activity->log($owner, 'project.created', "created project {$project->name}", $project, $project);

            return $project;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, Project $project, array $data): Project
    {
        $project->update($data);

        $this->activity->log($actor, 'project.updated', "updated project {$project->name}", $project, $project);

        return $project;
    }

    public function delete(User $actor, Project $project): void
    {
        $project->delete();

        $this->activity->log($actor, 'project.deleted', "deleted project {$project->name}", $project, $project);
    }

    public function updateWiki(User $actor, Project $project, ?string $wiki): Project
    {
        $project->update(['wiki' => $wiki]);

        $this->activity->log($actor, 'project.wiki_updated', "updated the {$project->name} wiki", $project, $project);

        return $project;
    }

    public function addMember(User $actor, Project $project, User $member, ProjectRole $role): void
    {
        if ($role === ProjectRole::Owner) {
            throw ValidationException::withMessages(['role' => 'A project can only have one owner.']);
        }

        $project->members()->syncWithoutDetaching([$member->id => ['role' => $role->value]]);

        $this->activity->log($actor, 'project.member_added', "added {$member->name} to {$project->name}", $member, $project);
    }

    public function removeMember(User $actor, Project $project, User $member): void
    {
        if ($member->id === $project->owner_id) {
            throw ValidationException::withMessages(['user' => 'The project owner cannot be removed.']);
        }

        DB::transaction(function () use ($project, $member) {
            $project->members()->detach($member->id);

            // Unassign them from this project's tasks as well.
            $member->tasks()->detach($project->tasks()->pluck('id'));
        });

        $this->activity->log($actor, 'project.member_removed', "removed {$member->name} from {$project->name}", $member, $project);
    }
}
