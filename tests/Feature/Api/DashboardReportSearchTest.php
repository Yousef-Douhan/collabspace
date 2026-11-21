<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class DashboardReportSearchTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'Me']);
        $project = $this->projectWith($this->me);
        $project->update(['name' => 'Alpha']);

        Task::factory()->create(['project_id' => $project->id, 'title' => 'Login screen']);
        Task::factory()->status(TaskStatus::InProgress)->create(['project_id' => $project->id]);
        Task::factory()->status(TaskStatus::Completed)->create(['project_id' => $project->id])->assignees()->attach($this->me);
        Task::factory()->overdue()->create(['project_id' => $project->id, 'start_date' => now()->subWeek()])->assignees()->attach($this->me);

        // Noise from a project I can't see.
        Task::factory()->count(3)->create(['title' => 'Login secret']);

        Sanctum::actingAs($this->me);
    }

    public function test_dashboard_counts_only_visible_work(): void
    {
        $this->getJson('/api/v1/dashboard?period=all')
            ->assertOk()
            ->assertJsonPath('data.stats.total', 4)
            ->assertJsonPath('data.stats.todo', 2)
            ->assertJsonPath('data.stats.in_progress', 1)
            ->assertJsonPath('data.stats.completed', 1)
            ->assertJsonPath('data.stats.completion_rate', 25)
            ->assertJsonPath('data.stats.overdue', 1)
            ->assertJsonPath('data.active_projects.0.name', 'Alpha')
            ->assertJsonPath('data.active_projects.0.completion_percentage', 25)
            ->assertJsonStructure(['data' => ['priority_tasks', 'team_status', 'upcoming_meetings', 'recent_files', 'recent_activity', 'unread_notifications']]);
    }

    public function test_report_overview_and_csv_export(): void
    {
        $this->getJson('/api/v1/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.tasks', 4)
            ->assertJsonPath('data.totals.overdue_tasks', 1)
            ->assertJsonPath('data.project_progress.0.project', 'Alpha')
            ->assertJsonPath('data.team_performance.0.name', 'Me')
            ->assertJsonPath('data.team_performance.0.assigned', 2)
            ->assertJsonPath('data.team_performance.0.completion_rate', 50);

        $report = $this->postJson('/api/v1/reports', ['type' => 'team_performance', 'notes' => 'Week 47'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'team_performance')
            ->json('data');

        $csv = $this->get($report['export_url'])->assertOk()->streamedContent();
        $this->assertStringContainsString('user_id,name,track,assigned,completed,overdue,completion_rate', $csv);
        $this->assertStringContainsString('Me', $csv);

        // Reports are private to their creator.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/reports/{$report['id']}")->assertForbidden();
        $this->getJson('/api/v1/reports')->assertJsonCount(0, 'data');
    }

    public function test_search_only_returns_visible_items(): void
    {
        $this->getJson('/api/v1/search?q=login')
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.title', 'Login screen');

        $this->getJson('/api/v1/search?q=a')->assertUnprocessable();
    }

    public function test_meta_lists_enums(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.task_statuses.1', ['value' => 'in_progress', 'label' => 'In Progress'])
            ->assertJsonCount(4, 'data.tracks');
    }
}
