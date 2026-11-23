<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MeetingResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Http\Resources\MeetingAttachmentResource;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\MeetingAttachment;
use App\Services\MeetingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingController extends Controller
{
    public function __construct(private MeetingService $meetings) {}

    /** GET /meetings?range=today|week|upcoming|past&project_id= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $range = $request->input('range', 'upcoming');

        $query = Meeting::visibleTo($request->user())
            ->with(['project:id,name', 'creator', 'attendees'])
            ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')));

        match ($range) {
            'today' => $query->whereBetween('starts_at', [today(), today()->endOfDay()])->orderBy('starts_at'),
            'week' => $query->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->orderBy('starts_at'),
            'past' => $query->where('ends_at', '<', now())->orderByDesc('starts_at'),
            'all' => $query->orderByDesc('starts_at'),
            default => $query->where('ends_at', '>=', now())->orderBy('starts_at'),
        };

        return MeetingResource::collection($query->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $meeting = $this->meetings->create($request->user(), $request->validated());

        return $this->resource($meeting)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Meeting $meeting): MeetingResource
    {
        Gate::authorize('view', $meeting);

        return $this->resource($meeting);
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): MeetingResource
    {
        $this->meetings->update($request->user(), $meeting, $request->validated());

        return $this->resource($meeting);
    }

    public function destroy(Meeting $meeting): Response
    {
        Gate::authorize('delete', $meeting);

        $meeting->delete();

        return response()->noContent();
    }

    /** POST /meetings/{meeting}/respond  {response: accepted|declined} */
    public function respond(Request $request, Meeting $meeting): MeetingResource
    {
        Gate::authorize('respond', $meeting);

        $data = $request->validate([
            'response' => ['required', Rule::enum(MeetingResponse::class)->except([MeetingResponse::Pending])],
        ]);

        $this->meetings->respond($request->user(), $meeting, MeetingResponse::from($data['response']));

        return $this->resource($meeting);
    }

    /** POST /meetings/{meeting}/summary — AI summary + key points. */
    public function summarize(Request $request, Meeting $meeting): MeetingResource|JsonResponse
    {
        Gate::authorize('summarize', $meeting);

        try {
            $this->meetings->summarize($request->user(), $meeting);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->resource($meeting);
    }

    /** PUT /meetings/{meeting}/notes  {notes} — shared notes ("Note" tab); attendees and organizers. */
    public function updateNotes(Request $request, Meeting $meeting): MeetingResource
    {
        Gate::authorize('summarize', $meeting);

        $data = $request->validate(['notes' => ['present', 'nullable', 'string', 'max:20000']]);
        $meeting->update(['notes' => $data['notes']]);

        return $this->resource($meeting);
    }

    /** POST /meetings/{meeting}/attachments  (multipart: file) */
    public function storeAttachment(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('summarize', $meeting);

        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $request->file('file');

        $attachment = $meeting->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store("meetings/{$meeting->id}", 'local'),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return (new MeetingAttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function downloadAttachment(MeetingAttachment $attachment): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $attachment->meeting);

        if (! Storage::disk('local')->exists($attachment->path)) {
            return response()->json(['message' => 'The file is missing from storage.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, MeetingAttachment $attachment): Response
    {
        abort_unless(
            $attachment->uploaded_by === $request->user()->id || Gate::allows('update', $attachment->meeting),
            Response::HTTP_FORBIDDEN,
        );

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    private function resource(Meeting $meeting): MeetingResource
    {
        return new MeetingResource(
            $meeting->fresh()
                ->load(['project:id,name', 'creator', 'attendees', 'attachments.uploader'])
                ->loadCount('comments')
        );
    }
}
