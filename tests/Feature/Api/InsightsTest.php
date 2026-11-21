<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    public function test_task_reviewer_is_stored_and_returned(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $dev = $this->member($project);
        $lead = $this->member($project);
        Sanctum::actingAs($owner);

        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'Login', 'priority' => 'high', 'track' => 'front_end',
            'assignees' => [$dev->id], 'reviewer_id' => $lead->id, 'estimated_hours' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.reviewer.id', $lead->id)
            ->assertJsonPath('data.estimated_hours', 6)
            ->json('data');

        $this->patchJson("/api/v1/tasks/{$task['id']}", ['reviewer_id' => $owner->id])
            ->assertOk()
            ->assertJsonPath('data.reviewer.id', $owner->id);

        $this->patchJson("/api/v1/tasks/{$task['id']}", ['reviewer_id' => null])
            ->assertOk()
            ->assertJsonPath('data.reviewer', null);
    }

    public function test_dashboard_compares_with_last_month_and_returns_chart_series(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(10));
        $me = User::factory()->create();
        $project = $this->projectWith($me);

        $this->travel(-1)->months();
        Task::factory()->count(2)->create(['project_id' => $project->id]);   // last month: 2 to-do
        $this->travel(1)->months();
        Task::factory()->count(3)->create(['project_id' => $project->id]);   // this month: 3 to-do

        Sanctum::actingAs($me);
        $this->getJson('/api/v1/dashboard?period=all')
            ->assertOk()
            ->assertJsonPath('data.changes.todo', 50)
            ->assertJsonCount(6, 'data.charts.project_overview')
            ->assertJsonPath('data.charts.project_overview.5.created', 3)
            ->assertJsonPath('data.charts.project_overview.4.created', 2);
    }

    public function test_report_has_kpis_and_charts(): void
    {
        $me = User::factory()->create();
        $project = $this->projectWith($me);
        Task::factory()->status(TaskStatus::Completed)->create(['project_id' => $project->id, 'due_date' => now()->addDay()]);
        Task::factory()->create(['project_id' => $project->id]);
        Sanctum::actingAs($me);

        $this->getJson('/api/v1/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.kpis.task_completion_rate.value', 50)
            ->assertJsonCount(6, 'data.charts.completion_vs_deadline')
            ->assertJsonPath('data.charts.completion_vs_deadline.5.on_time', 1)
            ->assertJsonPath('data.charts.projects_progress.5.progress', 50)
            ->assertJsonStructure(['data' => ['kpis' => ['team_activity_rate' => ['value', 'change_points']], 'charts' => ['worker_activity']]]);
    }

    public function test_profile_reports_hours_worked_and_recent_activity(): void
    {
        $me = User::factory()->create();
        $project = $this->projectWith($me);
        Task::factory()->status(TaskStatus::Completed)->create(['project_id' => $project->id, 'estimated_hours' => 5])->assignees()->attach($me);
        Task::factory()->create(['project_id' => $project->id, 'estimated_hours' => 40])->assignees()->attach($me); // not done → not counted

        $meeting = Meeting::factory()->create([
            'created_by' => $me->id,
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHours(3)->addMinutes(90),
        ]);
        $meeting->attendees()->attach($me->id, ['response' => 'accepted']);

        Sanctum::actingAs($me);
        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => User::factory()->create()->id])->assertOk();

        $this->getJson("/api/v1/users/{$me->id}")
            ->assertOk()
            ->assertJsonPath('data.stats.hours_worked', 6)   // 5h task + 1h of the 1.5h meeting
            ->assertJsonPath('data.recent_activity.0.action', 'project.member_added');
    }
}
