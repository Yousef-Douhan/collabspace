<?php

namespace App\Http\Controllers\Web;

use App\Enums\MeetingResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Models\Meeting;
use App\Models\MeetingAttachment;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingController extends Controller
{
    public function __construct(private MeetingService $meetings) {}

    /** Weekly calendar. ?week=YYYY-MM-DD (any day of the week to show). */
    public function index(Request $request): View
    {
        $start = ($request->filled('week') ? Carbon::parse($request->input('week')) : now())->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->addDays(6)->endOfDay();

        $meetings = Meeting::visibleTo($request->user())
            ->with(['attendees', 'project:id,name'])
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get();

        return view('meetings.index', [
            'start' => $start,
            'days' => collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i)),
            'meetings' => $meetings,
            'upcoming' => Meeting::visibleTo($request->user())->where('ends_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'projects' => Project::visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $meeting = $this->meetings->create($request->user(), $request->validated());

        return redirect()->route('meetings.show', $meeting)->with('status', __('Meeting scheduled.'));
    }

    public function show(Request $request, Meeting $meeting): View
    {
        Gate::authorize('view', $meeting);

        $meeting->load(['project:id,name', 'creator', 'attendees', 'comments.user', 'attachments.uploader']);
        $user = $request->user();

        return view('meetings.show', [
            'meeting' => $meeting,
            'tab' => in_array($request->input('tab'), ['summary', 'notes', 'comments'], true) ? $request->input('tab') : 'summary',
            'myResponse' => $meeting->attendees->firstWhere('id', $user->id)?->pivot->response,
            'canEdit' => $user->can('update', $meeting),
            'canSummarize' => $user->can('summarize', $meeting),
        ]);
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->meetings->update($request->user(), $meeting, $request->validated());

        return back()->with('status', __('Meeting updated.'));
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('delete', $meeting);

        $meeting->delete();

        return redirect()->route('meetings.index')->with('status', __('Meeting cancelled.'));
    }

    public function respond(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('respond', $meeting);

        $data = $request->validate(['response' => ['required', 'in:accepted,declined']]);
        $this->meetings->respond($request->user(), $meeting, MeetingResponse::from($data['response']));

        return back()->with('status', __('Response saved.'));
    }

    public function summarize(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('summarize', $meeting);

        try {
            $this->meetings->summarize($request->user(), $meeting);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('status', __('AI summary generated.'));
    }

    public function updateNotes(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('summarize', $meeting);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:20000']]);
        $meeting->update(['notes' => $data['notes'] ?? null]);

        return back()->with('status', __('Notes saved.'));
    }

    public function comment(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('view', $meeting);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $meeting->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return redirect()->route('meetings.show', [$meeting, 'tab' => 'comments']);
    }

    public function storeAttachment(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('summarize', $meeting);

        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $request->file('file');

        $meeting->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store("meetings/{$meeting->id}", 'local'),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('status', __('Attachment uploaded.'));
    }

    public function downloadAttachment(MeetingAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->meeting);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, MeetingAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->uploaded_by === $request->user()->id || Gate::allows('update', $attachment->meeting), 403);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }
}
