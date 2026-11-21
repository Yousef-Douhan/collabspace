<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Meeting;
use App\Models\Task;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Comments on tasks (Kanban cards) and meetings (the "Comments" tab). */
class CommentController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function taskIndex(Task $task): AnonymousResourceCollection
    {
        return $this->list($task);
    }

    public function taskStore(Request $request, Task $task): JsonResponse
    {
        $comment = $this->create($request, $task);

        $this->activity->log($request->user(), 'task.commented', "commented on \"{$task->title}\"", $task, $task->project);

        return (new CommentResource($comment))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function meetingIndex(Meeting $meeting): AnonymousResourceCollection
    {
        return $this->list($meeting);
    }

    public function meetingStore(Request $request, Meeting $meeting): JsonResponse
    {
        $comment = $this->create($request, $meeting);

        return (new CommentResource($comment))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /** PATCH /comments/{comment} — author only. */
    public function update(Request $request, Comment $comment): CommentResource
    {
        Gate::authorize('update', $comment);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment->update(['body' => $data['body'], 'edited_at' => now()]);

        return new CommentResource($comment->load('user'));
    }

    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }

    private function list(Task|Meeting $commentable): AnonymousResourceCollection
    {
        Gate::authorize('view', $commentable);

        return CommentResource::collection($commentable->comments()->with('user')->get());
    }

    private function create(Request $request, Task|Meeting $commentable): Comment
    {
        Gate::authorize('view', $commentable);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        /** @var Model&Comment $comment */
        $comment = $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return $comment->load('user');
    }
}
