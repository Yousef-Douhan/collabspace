<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    public function test_members_comment_on_tasks_and_the_card_shows_the_count(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $dev = $this->member($project);
        Sanctum::actingAs($owner);
        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'Board', 'priority' => 'high', 'track' => 'front_end',
        ])->json('data');

        Sanctum::actingAs($dev);
        $comment = $this->postJson("/api/v1/tasks/{$task['id']}/comments", ['body' => 'Looks good!'])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true)
            ->json('data');

        $this->getJson("/api/v1/tasks/{$task['id']}")->assertJsonPath('data.comments_count', 1);
        $this->getJson("/api/v1/tasks/{$task['id']}/comments")->assertJsonCount(1, 'data');

        // Only the author edits; the edit is flagged.
        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/comments/{$comment['id']}", ['body' => 'Hijack'])->assertForbidden();

        Sanctum::actingAs($dev);
        $this->patchJson("/api/v1/comments/{$comment['id']}", ['body' => 'Looks great!'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Looks great!')
            ->assertJsonPath('data.is_edited', true);

        $this->deleteJson("/api/v1/comments/{$comment['id']}")->assertNoContent();
        $this->getJson("/api/v1/tasks/{$task['id']}/comments")->assertJsonCount(0, 'data');
    }

    public function test_outsiders_cannot_read_or_write_task_comments(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        Sanctum::actingAs($owner);
        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'Secret', 'priority' => 'low', 'track' => 'back_end',
        ])->json('data');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/tasks/{$task['id']}/comments")->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task['id']}/comments", ['body' => 'hi'])->assertForbidden();
    }

    public function test_meeting_notes_comments_and_attachments(): void
    {
        Storage::fake('local');
        $host = User::factory()->create();
        $guest = User::factory()->create();
        Sanctum::actingAs($host);
        $meeting = $this->postJson('/api/v1/meetings', [
            'title' => 'Design review',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
            'attendees' => [$guest->id],
        ])->json('data');

        Sanctum::actingAs($guest);
        $this->putJson("/api/v1/meetings/{$meeting['id']}/notes", ['notes' => '- decide colors'])
            ->assertOk()
            ->assertJsonPath('data.notes', '- decide colors');
        $this->postJson("/api/v1/meetings/{$meeting['id']}/comments", ['body' => 'I will bring the mockups'])->assertCreated();
        $file = $this->post("/api/v1/meetings/{$meeting['id']}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('agenda.txt', 'Agenda'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->getJson("/api/v1/meetings/{$meeting['id']}")
            ->assertOk()
            ->assertJsonPath('data.comments_count', 1)
            ->assertJsonPath('data.attachments.0.name', 'agenda.txt');
        $this->assertSame('Agenda', $this->get($file['download_url'])->assertOk()->streamedContent());

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($file['download_url'])->assertForbidden();
        $this->putJson("/api/v1/meetings/{$meeting['id']}/notes", ['notes' => 'x'])->assertForbidden();
    }
}
