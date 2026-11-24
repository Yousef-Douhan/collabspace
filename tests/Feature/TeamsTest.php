<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

/** Teams: CRUD, lead / member roles, and linking a team to a project. */
class TeamsTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    private function team(User $lead, array $members = []): Team
    {
        return app(TeamService::class)->create($lead, ['name' => 'Squad '.fake()->unique()->word(), 'members' => collect($members)->pluck('id')->all()]);
    }

    private function ownedProject(User $owner): Project
    {
        return $this->projectWith($owner, [[$owner, ProjectRole::Owner]]);
    }

    public function test_a_manager_creates_a_team_and_becomes_its_lead(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $mona = User::factory()->create();

        $this->actingAs($manager)->post(route('teams.store'), ['name' => 'Backend Squad', 'description' => 'APIs', 'members' => [$mona->id]])
            ->assertRedirect();

        $team = Team::where('name', 'Backend Squad')->firstOrFail();
        $this->assertTrue($team->isLedBy($manager));
        $this->assertSame(TeamRole::Member->value, $team->members()->whereKey($mona->id)->first()->pivot->role);

        $this->get(route('teams.index'))->assertOk()->assertSee('Backend Squad');
        $this->get(route('teams.show', $team))->assertOk()->assertSee($mona->name);
    }

    public function test_plain_members_cannot_create_teams_and_names_are_unique(): void
    {
        $this->actingAs(User::factory()->create())->post(route('teams.store'), ['name' => 'Nope'])->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->team($admin)->update(['name' => 'Taken']);
        $this->actingAs($admin)->post(route('teams.store'), ['name' => 'Taken'])->assertSessionHasErrors('name');
    }

    public function test_only_a_lead_or_admin_manages_the_team(): void
    {
        $lead = User::factory()->create(['role' => UserRole::Manager]);
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $team = $this->team($lead, [$member]);

        $this->actingAs($member)->put(route('teams.update', $team), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($member)->post(route('teams.members.store', $team), ['user_id' => $outsider->id])->assertForbidden();

        $this->actingAs($lead)->put(route('teams.update', $team), ['name' => 'Renamed'])->assertRedirect();
        $this->assertSame('Renamed', $team->fresh()->name);

        // Promote the member: now they can manage the team too.
        $this->actingAs($lead)->patch(route('teams.members.update', [$team, $member]), ['role' => 'lead'])->assertRedirect();
        $this->assertTrue($team->isLedBy($member));
        $this->actingAs($member)->post(route('teams.members.store', $team), ['user_id' => $outsider->id])->assertRedirect();
        $this->assertTrue($team->members()->whereKey($outsider->id)->exists());
    }

    public function test_a_team_always_keeps_at_least_one_lead(): void
    {
        $lead = User::factory()->create(['role' => UserRole::Manager]);
        $team = $this->team($lead);

        $this->actingAs($lead)->delete(route('teams.members.destroy', [$team, $lead]))->assertSessionHasErrors('user');
        $this->actingAs($lead)->patch(route('teams.members.update', [$team, $lead]), ['role' => 'member'])->assertSessionHasErrors('user');
        $this->assertTrue($team->isLedBy($lead));
    }

    public function test_linking_a_team_adds_its_members_to_the_project_and_new_members_follow(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Manager]);
        $project = $this->ownedProject($owner);
        $existing = $this->member($project, ProjectRole::Manager);
        $mona = User::factory()->create();
        $team = $this->team($owner, [$existing, $mona]);

        $this->actingAs($owner)->post(route('teams.projects.store', $team), ['project_id' => $project->id])->assertRedirect();

        $this->assertTrue($project->teams()->whereKey($team->id)->exists());
        $this->assertTrue($project->hasMember($mona));
        $this->assertSame(ProjectRole::Manager, $project->roleOf($existing)); // existing roles are kept

        $late = User::factory()->create();
        $this->actingAs($owner)->post(route('teams.members.store', $team), ['user_id' => $late->id])->assertRedirect();
        $this->assertTrue($project->fresh()->hasMember($late));

        $this->get(route('projects.show', [$project, 'tab' => 'teams']))->assertOk()->assertSee($team->name);

        // Unlinking keeps the people in the project.
        $this->actingAs($owner)->delete(route('teams.projects.destroy', [$team, $project]))->assertRedirect();
        $this->assertFalse($project->teams()->exists());
        $this->assertTrue($project->fresh()->hasMember($mona));
    }

    public function test_only_project_managers_can_link_teams(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Manager]);
        $project = $this->ownedProject($owner);
        $plain = $this->member($project);
        $team = $this->team($owner);

        $this->actingAs($plain)->post(route('teams.projects.store', $team), ['project_id' => $project->id])->assertForbidden();
    }

    public function test_the_teams_api(): void
    {
        $lead = User::factory()->create(['role' => UserRole::Manager]);
        $mona = User::factory()->create();
        $project = $this->ownedProject($lead);
        Sanctum::actingAs($lead);

        $id = $this->postJson('/api/v1/teams', ['name' => 'API Team', 'members' => [$mona->id]])
            ->assertCreated()->assertJsonPath('data.name', 'API Team')->json('data.id');

        $this->getJson('/api/v1/teams')->assertOk()->assertJsonPath('data.0.members_count', 2);
        $this->getJson("/api/v1/teams/{$id}")->assertOk()->assertJsonCount(2, 'data.members');
        $this->patchJson("/api/v1/teams/{$id}/members/{$mona->id}", ['role' => 'lead'])->assertOk();

        $this->postJson("/api/v1/projects/{$project->id}/teams", ['team_id' => $id])->assertCreated()->assertJsonPath('data.0.id', $id);
        $this->assertTrue($project->hasMember($mona));
        $this->deleteJson("/api/v1/projects/{$project->id}/teams/{$id}")->assertNoContent();

        $this->deleteJson("/api/v1/teams/{$id}/members/{$mona->id}")->assertNoContent();
        $this->deleteJson("/api/v1/teams/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('teams', ['id' => $id]);
    }
}
