<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\File\StoreFileRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private FileService $files) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $base = ProjectFile::visibleTo($user)->with(['project:id,name', 'uploader']);

        return view('files.index', [
            'recent' => (clone $base)->latest()->limit(4)->get(),
            'files' => $base
                ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')))
                ->when($request->filled('q'), fn (Builder $q) => $q->where('original_name', 'like', '%'.$request->input('q').'%'))
                ->latest()
                ->paginate(18)
                ->withQueryString(),
            'projects' => Project::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreFileRequest $request): RedirectResponse
    {
        $task = $request->filled('task_id') ? Task::find($request->integer('task_id')) : null;
        $file = $this->files->upload($request->user(), Project::findOrFail($request->integer('project_id')), $request->file('file'), $task);

        return back()->with('status', __('":name" uploaded.', ['name' => $file->original_name]));
    }

    public function download(ProjectFile $file): StreamedResponse
    {
        Gate::authorize('view', $file);
        abort_unless(Storage::disk(FileService::DISK)->exists($file->path), 404, __('The file is missing from storage.'));

        return Storage::disk(FileService::DISK)->download($file->path, $file->original_name);
    }

    public function destroy(Request $request, ProjectFile $file): RedirectResponse
    {
        Gate::authorize('delete', $file);

        $this->files->delete($request->user(), $file);

        return back()->with('status', __('File deleted.'));
    }
}
