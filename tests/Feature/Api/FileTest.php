<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class FileTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->owner = User::factory()->create();
        $this->project = $this->projectWith($this->owner);
    }

    private function upload(array $overrides = []): array
    {
        return $this->post('/api/v1/files', [
            'project_id' => $this->project->id,
            'file' => UploadedFile::fake()->createWithContent('scope.txt', 'Alpha scope'),
            ...$overrides,
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    public function test_member_can_upload_and_download_a_file(): void
    {
        Sanctum::actingAs($this->owner);

        $file = $this->upload();

        $this->assertSame('scope.txt', $file['name']);
        $this->assertSame(11, $file['size_bytes']);
        Storage::disk('local')->assertExists("projects/{$this->project->id}/".basename(\App\Models\ProjectFile::find($file['id'])->path));

        $download = $this->get($file['download_url'])->assertOk();
        $this->assertSame('Alpha scope', $download->streamedContent());
        $this->assertStringContainsString('scope.txt', $download->headers->get('content-disposition'));
    }

    public function test_files_are_not_publicly_reachable(): void
    {
        Sanctum::actingAs($this->owner);
        $file = $this->upload();

        // Unauthenticated download is refused.
        $this->app['auth']->forgetGuards();
        $this->getJson($file['download_url'])->assertUnauthorized();
    }

    public function test_non_members_cannot_download_or_list(): void
    {
        Sanctum::actingAs($this->owner);
        $file = $this->upload();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($file['download_url'])->assertForbidden();
        $this->getJson('/api/v1/files')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_task_must_belong_to_the_same_project(): void
    {
        Sanctum::actingAs($this->owner);
        $foreignTask = Task::factory()->create();

        $this->post('/api/v1/files', [
            'project_id' => $this->project->id,
            'task_id' => $foreignTask->id,
            'file' => UploadedFile::fake()->create('a.pdf', 10),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['task_id']);
    }

    public function test_only_uploader_or_managers_can_delete(): void
    {
        $uploader = $this->member($this->project);
        $other = $this->member($this->project);
        Sanctum::actingAs($uploader);
        $file = $this->upload();
        $path = \App\Models\ProjectFile::find($file['id'])->path;

        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/files/{$file['id']}")->assertForbidden();

        Sanctum::actingAs($this->owner); // project owner = manager
        $this->deleteJson("/api/v1/files/{$file['id']}")->assertNoContent();
        $this->assertDatabaseMissing('files', ['id' => $file['id']]);
        Storage::disk('local')->assertMissing($path);
    }
}
