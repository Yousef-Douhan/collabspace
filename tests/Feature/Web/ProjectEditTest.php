<?php

namespace Tests\Feature\Web;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class ProjectEditTest extends TestCase
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

    public function test_the_owner_sees_the_edit_button_and_can_save_changes(): void
    {
        $this->actingAs($this->owner)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Edit project')
            ->assertSee('Delete project');

        $this->actingAs($this->owner)
            ->put(route('projects.update', $this->project), [
                'name' => 'Alpha v2',
                'description' => 'New scope',
                'status' => ProjectStatus::Completed->value,
                'priority' => 'high',
                'start_date' => '2026-11-01',
                'end_date' => '2026-12-20',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $this->project->fresh();
        $this->assertSame('Alpha v2', $fresh->name);
        $this->assertSame(ProjectStatus::Completed, $fresh->status);
        $this->assertSame('2026-12-20', $fresh->end_date->format('Y-m-d'));
    }

    public function test_a_deadline_before_the_start_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->put(route('projects.update', $this->project), ['start_date' => '2026-12-10', 'end_date' => '2026-12-01'])
            ->assertSessionHasErrors('end_date');
    }

    public function test_a_plain_member_cannot_edit_and_sees_no_button(): void
    {
        $member = $this->member($this->project, ProjectRole::Member);

        $this->actingAs($member)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertDontSee('Edit project');

        $this->actingAs($member)
            ->put(route('projects.update', $this->project), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $this->project->fresh()->name);
    }

    public function test_the_owner_can_delete_the_project(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('projects.destroy', $this->project))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($this->project);
    }
}
