<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = $this->projectWith($this->owner);
    }

    private function createTask(array $overrides = []): array
    {
        return $this->postJson('/api/v1/tasks', [
            'project_id' => $this->project->id,
            'title' => 'Build the board',
            'priority' => 'high',
            'track' => 'back_end',
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    /** @return list<int> task ids of a column, top to bottom */
    private function column(TaskStatus $status): array
    {
        return Task::where('project_id', $this->project->id)
            ->where('status', $status)
            ->orderBy('position')
            ->pluck('id')
            ->all();
    }

    public function test_member_can_create_a_task_with_assignees(): void
    {
        $dev = $this->member($this->project);
        Sanctum::actingAs($this->owner);

        $task = $this->createTask(['assignees' => [$dev->id]]);

        $this->assertSame('todo', $task['status']);
        $this->assertSame(0, $task['position']);
        $this->assertSame($dev->id, $task['assignees'][0]['id']);
        $this->assertSame($this->owner->id, $task['creator']['id']);

        $second = $this->createTask(['title' => 'Second']);
        $this->assertSame(1, $second['position'], 'New tasks go to the end of their column.');
    }

    public function test_non_members_cannot_create_tasks(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/tasks', [
            'project_id' => $this->project->id,
            'title' => 'Sneaky',
            'priority' => 'low',
            'track' => 'back_end',
        ])->assertForbidden();
    }

    public function test_assignees_must_be_project_members(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $this->project->id,
            'title' => 'Task',
            'priority' => 'low',
            'track' => 'back_end',
            'assignees' => [User::factory()->create()->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['assignees.0']);
    }

    public function test_creating_a_completed_task_sets_progress_and_completed_at(): void
    {
        Sanctum::actingAs($this->owner);

        $task = $this->createTask(['status' => 'completed']);

        $this->assertSame(100, $task['progress']);
        $this->assertNotNull($task['completed_at']);
    }

    public function test_board_view_groups_tasks_by_status_in_position_order(): void
    {
        Sanctum::actingAs($this->owner);
        $a = $this->createTask(['title' => 'A']);
        $b = $this->createTask(['title' => 'B']);
        $this->createTask(['title' => 'C', 'status' => 'in_review']);

        $this->getJson("/api/v1/tasks?view=board&project_id={$this->project->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.todo')
            ->assertJsonCount(0, 'data.in_progress')
            ->assertJsonCount(1, 'data.in_review')
            ->assertJsonCount(0, 'data.completed')
            ->assertJsonPath('data.todo.0.id', $a['id'])
            ->assertJsonPath('data.todo.1.id', $b['id']);
    }

    public function test_moving_a_task_reorders_both_columns(): void
    {
        Sanctum::actingAs($this->owner);
        $a = $this->createTask(['title' => 'A']);
        $b = $this->createTask(['title' => 'B']);
        $c = $this->createTask(['title' => 'C']);

        // Reorder inside the same column: C to the top.
        $this->patchJson("/api/v1/tasks/{$c['id']}/move", ['status' => 'todo', 'position' => 0])->assertOk();
        $this->assertSame([$c['id'], $a['id'], $b['id']], $this->column(TaskStatus::Todo));

        // Move A to Completed: the gap in To-do closes, A is completed.
        $this->patchJson("/api/v1/tasks/{$a['id']}/move", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.position', 0);

        $this->assertSame([$c['id'], $b['id']], $this->column(TaskStatus::Todo));
        $this->assertSame([0, 1], Task::where('status', TaskStatus::Todo)->orderBy('position')->pluck('position')->all());
        $this->assertNotNull(Task::find($a['id'])->completed_at);

        // Moving it back out of Completed clears completed_at.
        $this->patchJson("/api/v1/tasks/{$a['id']}/move", ['status' => 'in_progress'])->assertOk();
        $this->assertNull(Task::find($a['id'])->completed_at);
    }

    public function test_out_of_range_positions_are_clamped(): void
    {
        Sanctum::actingAs($this->owner);
        $a = $this->createTask(['title' => 'A']);
        $this->createTask(['title' => 'B']);

        $this->patchJson("/api/v1/tasks/{$a['id']}/move", ['status' => 'todo', 'position' => 99])
            ->assertOk()
            ->assertJsonPath('data.position', 1);
    }

    public function test_only_assignees_creator_or_managers_can_update(): void
    {
        $assignee = $this->member($this->project);
        $bystander = $this->member($this->project);
        Sanctum::actingAs($this->owner);
        $task = $this->createTask(['assignees' => [$assignee->id]]);

        Sanctum::actingAs($bystander);
        $this->patchJson("/api/v1/tasks/{$task['id']}", ['progress' => 50])->assertForbidden();
        $this->patchJson("/api/v1/tasks/{$task['id']}/move", ['status' => 'completed'])->assertForbidden();

        Sanctum::actingAs($assignee);
        $this->patchJson("/api/v1/tasks/{$task['id']}", ['progress' => 50, 'status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.progress', 50)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_only_creator_or_managers_can_delete(): void
    {
        $assignee = $this->member($this->project);
        Sanctum::actingAs($this->owner);
        $task = $this->createTask(['assignees' => [$assignee->id]]);

        Sanctum::actingAs($assignee);
        $this->deleteJson("/api/v1/tasks/{$task['id']}")->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v1/tasks/{$task['id']}")->assertNoContent();
        $this->assertSoftDeleted('tasks', ['id' => $task['id']]);
    }

    public function test_filters_my_tasks_and_overdue(): void
    {
        $dev = $this->member($this->project, ProjectRole::Manager);
        Sanctum::actingAs($this->owner);
        $mine = $this->createTask(['title' => 'Mine', 'assignees' => [$dev->id]]);
        $this->createTask(['title' => 'Not mine']);
        $late = $this->createTask(['title' => 'Late', 'start_date' => now()->subWeek()->toDateString(), 'due_date' => now()->subDays(2)->toDateString()]);

        Sanctum::actingAs($dev);
        $this->getJson('/api/v1/tasks?assignee=me')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine['id']);

        $this->getJson('/api/v1/tasks?overdue=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $late['id'])
            ->assertJsonPath('data.0.is_overdue', true);
    }

    public function test_users_never_see_tasks_from_other_projects(): void
    {
        Task::factory()->create(); // another project
        Sanctum::actingAs($this->owner);
        $this->createTask();

        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(1, 'data');
    }
}
