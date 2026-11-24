<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teams and their link to projects.
 *
 * Linking a team to a project adds every member to the project (existing project roles are
 * kept); someone who later joins the team joins its projects too. Unlinking a team, or
 * leaving one, never removes anybody from a project: that stays an explicit project action.
 */
class TeamService
{
    public function __construct(private ActivityLogger $activity) {}

    /** @param  array{name: string, description?: ?string, members?: list<int>}  $data */
    public function create(User $creator, array $data): Team
    {
        return DB::transaction(function () use ($creator, $data) {
            $team = Team::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'created_by' => $creator->id]);

            $members = collect($data['members'] ?? [])->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $creator->id)
                ->mapWithKeys(fn (int $id) => [$id => ['role' => TeamRole::Member->value]]);
            $team->members()->attach([$creator->id => ['role' => TeamRole::Lead->value], ...$members->all()]);

            $this->activity->log($creator, 'team.created', "created team {$team->name}", $team);

            return $team;
        });
    }

    /** @param  array{name?: string, description?: ?string}  $data */
    public function update(User $actor, Team $team, array $data): Team
    {
        $team->update($data);
        $this->activity->log($actor, 'team.updated', "updated team {$team->name}", $team);

        return $team;
    }

    public function delete(User $actor, Team $team): void
    {
        $team->delete();
        $this->activity->log($actor, 'team.deleted', "deleted team {$team->name}");
    }

    public function addMember(User $actor, Team $team, User $member, TeamRole $role = TeamRole::Member): void
    {
        DB::transaction(function () use ($team, $member, $role) {
            $team->members()->syncWithoutDetaching([$member->id => ['role' => $role->value]]);

            // Joining a team means joining the projects it works on.
            $team->projects()->each(fn (Project $project) => $this->joinProject($project, [$member->id]));
        });

        $this->activity->log($actor, 'team.member_added', "added {$member->name} to team {$team->name}", $member);
    }

    public function changeRole(User $actor, Team $team, User $member, TeamRole $role): void
    {
        if ($role === TeamRole::Member) {
            $this->guardLastLead($team, $member);
        }

        $team->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        $this->activity->log($actor, 'team.role_changed', "made {$member->name} {$role->value} of team {$team->name}", $member);
    }

    public function removeMember(User $actor, Team $team, User $member): void
    {
        $this->guardLastLead($team, $member);

        $team->members()->detach($member->id);
        $this->activity->log($actor, 'team.member_removed', "removed {$member->name} from team {$team->name}", $member);
    }

    public function attachToProject(User $actor, Team $team, Project $project): void
    {
        DB::transaction(function () use ($team, $project) {
            $project->teams()->syncWithoutDetaching([$team->id]);
            $this->joinProject($project, $team->members()->pluck('users.id')->all());
        });

        $this->activity->log($actor, 'project.team_added', "added team {$team->name} to {$project->name}", $team, $project);
    }

    public function detachFromProject(User $actor, Team $team, Project $project): void
    {
        $project->teams()->detach($team->id);
        $this->activity->log($actor, 'project.team_removed', "removed team {$team->name} from {$project->name}", $team, $project);
    }

    /** Adds people to a project as members, without touching anyone already there. */
    private function joinProject(Project $project, array $userIds): void
    {
        $already = $project->members()->pluck('users.id')->all();
        $new = array_diff($userIds, $already);

        if ($new) {
            $project->members()->attach(array_fill_keys($new, ['role' => ProjectRole::Member->value]));
        }
    }

    private function guardLastLead(Team $team, User $member): void
    {
        if ($team->isLedBy($member) && $team->leads()->count() === 1) {
            throw ValidationException::withMessages(['user' => __('A team needs at least one lead. Make someone else lead first.')]);
        }
    }
}
