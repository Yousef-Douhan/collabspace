<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    public function test_assigning_a_task_notifies_the_assignee_and_can_be_read(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $project = $this->projectWith($owner);
        $dev = $this->member($project);

        Sanctum::actingAs($owner);
        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Write docs',
            'priority' => 'medium',
            'track' => 'back_end',
            'assignees' => [$owner->id, $dev->id],
        ])->assertCreated()->json('data');

        // The person assigning is not notified.
        $this->getJson('/api/v1/notifications')->assertJsonCount(0, 'data');

        Sanctum::actingAs($dev);
        $id = $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.type', 'task_assigned')
            ->assertJsonPath('data.0.data.task_id', $task['id'])
            ->json('data.0.id');

        $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications?unread=1')->assertJsonCount(0, 'data');
    }

    public function test_re_saving_the_same_assignees_does_not_notify_again(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $dev = $this->member($project);
        Sanctum::actingAs($owner);

        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'T', 'priority' => 'low', 'track' => 'back_end', 'assignees' => [$dev->id],
        ])->json('data');
        $this->patchJson("/api/v1/tasks/{$task['id']}", ['assignees' => [$dev->id]])->assertOk();

        $this->assertSame(1, $dev->notifications()->count());
    }

    public function test_users_cannot_touch_each_others_notifications(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $dev = $this->member($project);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'T', 'priority' => 'low', 'track' => 'back_end', 'assignees' => [$dev->id],
        ]);
        $id = $dev->notifications()->first()->id;

        $this->patchJson("/api/v1/notifications/{$id}/read")->assertNotFound();

        Sanctum::actingAs($dev);
        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->assertSame(0, $dev->unreadNotifications()->count());
    }
}
