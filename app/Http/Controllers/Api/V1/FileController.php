<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\File\StoreFileRequest;
use App\Http\Resources\FileResource;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private FileService $files) {}

    /** GET /files?project_id=&task_id=&q= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $files = ProjectFile::visibleTo($request->user())
            ->with(['project:id,name', 'uploader'])
            ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('task_id'), fn (Builder $q) => $q->where('task_id', $request->integer('task_id')))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('original_name', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return FileResource::collection($files);
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        $project = Project::findOrFail($request->integer('project_id'));
        $task = $request->filled('task_id') ? Task::find($request->integer('task_id')) : null;

        $file = $this->files->upload($request->user(), $project, $request->file('file'), $task);

        return (new FileResource($file->load(['project:id,name', 'uploader'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function download(ProjectFile $file): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $file);

        if (! Storage::disk(FileService::DISK)->exists($file->path)) {
            return response()->json(['message' => 'The file is missing from storage.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk(FileService::DISK)->download($file->path, $file->original_name);
    }

    public function destroy(Request $request, ProjectFile $file): Response
    {
        Gate::authorize('delete', $file);

        $this->files->delete($request->user(), $file);

        return response()->noContent();
    }
}
