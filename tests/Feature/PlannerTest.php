<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesAi;
use Tests\TestCase;

/** AI task planning: meeting → tasks, project description → plan, then bulk creation. */
class PlannerTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $admin;

    private User $sara;

    private Project $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $this->admin = User::where('email', 'admin@collabspace.test')->firstOrFail();
        $this->sara = User::where('email', 'sara@collabspace.test')->firstOrFail();
        $this->alpha = Project::where('name', 'Alpha')->firstOrFail();
    }

    private function aiTasks(array $tasks): void
    {
        $this->fakeAi(json_encode(['tasks' => $tasks]));
    }

    public function test_meeting_action_items_become_suggestions(): void
    {
        $meeting = Meeting::whereNotNull('transcript')->firstOrFail();
        $this->aiTasks([
            ['title' => 'Finish the move endpoint', 'description' => 'PATCH /tasks/{id}/move', 'track' => 'back_end', 'priority' => 'high', 'assignee_id' => $this->sara->id, 'due_in_days' => 2],
            ['title' => 'Review drag & drop', 'track' => 'nonsense', 'priority' => 'urgent', 'assignee_id' => 999999, 'due_in_days' => 'soon'],
            ['description' => 'no title — dropped'],
        ]);

        $tasks = $this->actingAs($this->admin)
            ->postJson(route('planner.meeting', $meeting))
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $tasks);
        $this->assertSame($this->sara->id, $tasks[0]['assignee_id']);
        $this->assertSame($meeting->starts_at->copy()->addDays(2)->toDateString(), $tasks[0]['due_date']);
        // Invalid values from the model are cleaned up, never trusted.
        $this->assertSame('back_end', $tasks[1]['track']);
        $this->assertSame('medium', $tasks[1]['priority']);
        $this->assertNull($tasks[1]['assignee_id']);
        $this->assertNull($tasks[1]['due_date']);
    }

    public function test_project_plan_suggestions(): void
    {
        $this->aiTasks([['title' => 'Design onboarding screens', 'track' => 'ui_ux', 'priority' => 'medium', 'assignee_id' => null, 'due_in_days' => 5]]);

        $this->actingAs($this->admin)
            ->postJson(route('planner.project', $this->alpha))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Design onboarding screens')
            ->assertJsonPath('data.0.track', 'ui_ux');
    }

    public function test_kept_suggestions_are_created_on_the_board(): void
    {
        Notification::fake();
        $before = $this->alpha->tasks()->count();

        $this->actingAs($this->admin)
            ->postJson(route('planner.create', $this->alpha), [
                'tasks' => [
                    ['title' => 'AI task one', 'track' => 'back_end', 'priority' => 'high', 'assignee_id' => $this->sara->id, 'due_date' => now()->addDays(3)->toDateString()],
                    ['title' => 'AI task two', 'track' => 'ui_ux', 'priority' => 'low', 'assignee_id' => null],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.created', 2);

        $this->assertSame($before + 2, $this->alpha->tasks()->count());
        $this->assertTrue(Task::where('title', 'AI task one')->firstOrFail()->assignees->contains($this->sara));
        Notification::assertSentTo($this->sara, TaskAssigned::class);
    }

    public function test_assignees_outside_the_project_are_ignored(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('planner.create', $this->alpha), [
                'tasks' => [['title' => 'Sneaky', 'track' => 'back_end', 'priority' => 'low', 'assignee_id' => $outsider->id]],
            ])
            ->assertCreated();

        $this->assertCount(0, Task::where('title', 'Sneaky')->firstOrFail()->assignees);
    }

    public function test_outsiders_cannot_plan_or_create(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson(route('planner.project', $this->alpha))->assertForbidden();
        $this->actingAs($outsider)
            ->postJson(route('planner.create', $this->alpha), ['tasks' => [['title' => 'x', 'track' => 'back_end', 'priority' => 'low']]])
            ->assertForbidden();
    }

    public function test_api_and_friendly_errors(): void
    {
        Sanctum::actingAs($this->admin);
        $this->fakeAi('not json at all');

        $this->postJson("/api/v1/projects/{$this->alpha->id}/plan")
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);

        $meeting = Meeting::create([
            'title' => 'No project', 'created_by' => $this->admin->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'notes' => 'Something',
        ]);
        $meeting->attendees()->attach($this->admin->id, ['response' => 'accepted']);

        $this->postJson("/api/v1/meetings/{$meeting->id}/plan")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('Link this meeting to a project first, so the tasks have a board to go to.'));
    }
}
