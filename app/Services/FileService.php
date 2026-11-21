<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Files live on the private "local" disk (storage/app/private) and are only
 * reachable through the authorized download endpoint — never by a public URL.
 */
class FileService
{
    public const DISK = 'local';

    public function __construct(private ActivityLogger $activity) {}

    public function upload(User $uploader, Project $project, UploadedFile $file, ?Task $task = null): ProjectFile
    {
        $path = $file->store("projects/{$project->id}", self::DISK);

        $projectFile = ProjectFile::create([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'uploaded_by' => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $this->activity->log($uploader, 'file.uploaded', "uploaded {$projectFile->original_name}", $projectFile, $project);

        return $projectFile;
    }

    public function delete(User $actor, ProjectFile $file): void
    {
        Storage::disk(self::DISK)->delete($file->path);
        $file->delete();

        $this->activity->log($actor, 'file.deleted', "deleted {$file->original_name}", null, $file->project);
    }
}
