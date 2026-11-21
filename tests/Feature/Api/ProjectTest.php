<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    public function test_index_lists_only_projects_the_user_belongs_to(): void
    {
        $me = User::factory()->create();
        $mine = $this->projectWith($me);
        Project::factory()->create(); // someone else's

        Sanctum::actingAs($me);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.my_role', 'owner');
    }

    public function test_admin_sees_every_project(): void
    {
        Project::factory()->count(2)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_a_project_and_becomes_its_owner(): void
    {
        $me = User::factory()->create();
        $teammate = User::factory()->create();
        Sanctum::actingAs($me);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Alpha',
            'priority' => 'high',
            'members' => [$teammate->id],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Alpha')
            ->assertJsonPath('data.my_role', 'owner')
            ->assertJsonPath('data.members_count', 2);

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertSame(ProjectRole::Owner, $project->roleOf($me));
        $this->assertSame(ProjectRole::Member, $project->roleOf($teammate));
        $this->assertDatabaseHas('activity_logs', ['action' => 'project.created', 'project_id' => $project->id]);
    }

    public function test_non_members_cannot_view_a_project(): void
    {
        $project = Project::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/projects/{$project->id}")->assertForbidden();
    }

    public function test_show_returns_members_wiki_and_completion(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $project->update(['wiki' => '# Notes']);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.wiki', '# Notes')
            ->assertJsonPath('data.completion_percentage', 0)
            ->assertJsonCount(1, 'data.members');
    }

    public function test_only_managers_can_update_a_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $member = $this->member($project);
        $manager = $this->member($project, ProjectRole::Manager);

        Sanctum::actingAs($member);
        $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Hacked'])->assertForbidden();

        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Alpha v2', 'status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Alpha v2')
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_only_the_owner_can_delete_and_it_is_soft_deleted(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $manager = $this->member($project, ProjectRole::Manager);

        Sanctum::actingAs($manager);
        $this->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();
        $this->assertSoftDeleted($project);
    }

    public function test_managers_can_add_and_remove_members_but_not_the_owner(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $newcomer = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $newcomer->id, 'role' => 'manager'])
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->assertSame(ProjectRole::Manager, $project->roleOf($newcomer));

        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $newcomer->id, 'role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$newcomer->id}")->assertNoContent();
        $this->assertFalse($project->hasMember($newcomer));

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$owner->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    public function test_plain_members_cannot_manage_members(): void
    {
        $project = $this->projectWith(User::factory()->create());
        $member = $this->member($project);
        Sanctum::actingAs($member);

        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => User::factory()->create()->id])
            ->assertForbidden();
    }

    public function test_any_member_can_edit_the_wiki(): void
    {
        $project = $this->projectWith(User::factory()->create());
        $member = $this->member($project);
        Sanctum::actingAs($member);

        $this->putJson("/api/v1/projects/{$project->id}/wiki", ['wiki' => "# Alpha\n- goal"])
            ->assertOk()
            ->assertJsonPath('data.wiki', "# Alpha\n- goal");
    }
}
