<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

class UploadSafetyTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $owner = User::factory()->create();
        $this->project = $this->projectWith($owner);
        Sanctum::actingAs($owner);
    }

    private function upload(string $name, string $content = 'data')
    {
        return $this->post('/api/v1/files', [
            'project_id' => $this->project->id,
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ], ['Accept' => 'application/json']);
    }

    public static function dangerousNames(): array
    {
        return [
            'php script' => ['shell.php'],
            'double extension' => ['invoice.php.pdf'],
            'html page' => ['page.html'],
            'svg with script' => ['logo.svg'],
            'windows executable' => ['setup.exe'],
            'upper-case extension' => ['SHELL.PHP'],
        ];
    }

    #[DataProvider('dangerousNames')]
    public function test_executable_or_scriptable_files_are_rejected(string $name): void
    {
        $this->upload($name, '<?php echo 1;')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        Storage::disk('local')->assertDirectoryEmpty("projects/{$this->project->id}");
    }

    public function test_ordinary_documents_are_still_accepted(): void
    {
        foreach (['scope.txt', 'brief.pdf', 'screen.png', 'js-notes.txt', 'budget.xlsx'] as $name) {
            $this->upload($name)->assertCreated();
        }
    }
}
