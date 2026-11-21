<?php

namespace Tests\Concerns;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

trait BuildsWorkspace
{
    /**
     * A project owned by $owner, with extra members: [User, ProjectRole] pairs.
     *
     * @param  list<array{0: User, 1: ProjectRole}>  $members
     */
    protected function projectWith(User $owner, array $members = []): Project
    {
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        foreach ($members as [$user, $role]) {
            $project->members()->attach($user->id, ['role' => $role->value]);
        }

        return $project;
    }

    protected function member(Project $project, ProjectRole $role = ProjectRole::Member): User
    {
        $user = User::factory()->create();
        $project->members()->attach($user->id, ['role' => $role->value]);

        return $user;
    }
}
