<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class UserTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    public function test_directory_can_be_filtered(): void
    {
        User::factory()->create(['name' => 'Lina', 'track' => 'ui_ux']);
        User::factory()->create(['name' => 'Omar', 'track' => 'front_end']);
        Sanctum::actingAs(User::factory()->create(['name' => 'Me', 'track' => 'back_end']));

        $this->getJson('/api/v1/users')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/users?track=ui_ux')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lina');
        $this->getJson('/api/v1/users?q=om')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Omar');
    }

    public function test_profile_shows_stats_and_only_tasks_the_viewer_may_see(): void
    {
        $me = User::factory()->create();
        $colleague = User::factory()->create();

        $shared = $this->projectWith($me, [[$colleague, \App\Enums\ProjectRole::Member]]);
        $sharedTask = Task::factory()->create(['project_id' => $shared->id]);
        $sharedTask->assignees()->attach($colleague);

        $secret = $this->projectWith($colleague);
        Task::factory()->create(['project_id' => $secret->id])->assignees()->attach($colleague);
        Task::factory()->status(TaskStatus::Completed)->create(['project_id' => $shared->id])->assignees()->attach($colleague);

        Sanctum::actingAs($me);

        $this->getJson("/api/v1/users/{$colleague->id}")
            ->assertOk()
            ->assertJsonPath('data.stats.open_tasks', 1)
            ->assertJsonPath('data.stats.completed_tasks', 1)
            ->assertJsonCount(1, 'data.shared_projects')
            ->assertJsonCount(1, 'data.open_tasks')
            ->assertJsonPath('data.open_tasks.0.id', $sharedTask->id);
    }
}
