<x-layouts.app :title="$meeting->title">
    <x-page-header :title="$meeting->title" :crumbs="['Meetings' => route('meetings.index')]">
        <x-slot:subtitle>
            <div class="mt-1 text-sm text-slate-500">{{ $meeting->starts_at->translatedFormat('l, M d') }} · <span dir="ltr">{{ $meeting->starts_at->format('H:i') }}–{{ $meeting->ends_at->format('H:i') }}</span>
                @if ($meeting->project) · <a href="{{ route('projects.show', $meeting->project) }}" class="text-brand">{{ $meeting->project->name }}</a>@endif
                @if ($meeting->location) · <x-icon name="map-pin" size="3.5" class="inline align-[-2px]" /> {{ $meeting->location }}@endif
            </div>
        </x-slot:subtitle>
        @if ($meeting->meeting_link)<a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener" class="btn-primary">{{ __('Join ↗') }}</a>@endif
        @if ($myResponse)
            @foreach (['accepted' => 'Accept', 'declined' => 'Decline'] as $value => $label)
                <form method="POST" action="{{ route('meetings.respond', $meeting) }}">@csrf
                    <input type="hidden" name="response" value="{{ $value }}">
                    <button @class(['btn-ghost', 'ring-2 ring-brand' => ($myResponse instanceof \BackedEnum ? $myResponse->value : $myResponse) === $value])>{{ __($label) }}</button>
                </form>
            @endforeach
        @endif
        @can('delete', $meeting)
            <form method="POST" action="{{ route('meetings.destroy', $meeting) }}" onsubmit="return confirm(@js(__('Cancel this meeting?')))">@csrf @method('DELETE')<button class="btn-danger whitespace-nowrap">{{ __('Cancel meeting') }}</button></form>
        @endcan
    </x-page-header>

    @error('ai')<div class="mb-4 rounded-xl bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $message }}</div>@enderror

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_380px]">
        <div class="space-y-5">
            <div class="flex gap-6 border-b border-slate-200">
                @foreach (['summary' => 'AI Summary', 'notes' => 'Note', 'comments' => 'Comments'] as $key => $label)
                    <a href="{{ route('meetings.show', [$meeting, 'tab' => $key]) }}" @class(['tab', 'tab-active' => $tab === $key])>{{ __($label) }}@if ($key === 'comments') <span class="text-slate-400">{{ $meeting->comments->count() }}</span>@endif</a>
                @endforeach
            </div>

            @if ($tab === 'summary')
                <div class="card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-semibold"><x-icon name="sparkles" class="text-amber-500" />{{ __('AI Summary') }}</h2>
                        @if ($canSummarize && $meeting->transcript)
                            <form method="POST" action="{{ route('meetings.summarize', $meeting) }}">@csrf
                                <button class="btn-ghost text-xs">{{ $meeting->ai_summary ? __('Regenerate') : __('Generate summary') }}</button>
                            </form>
                        @endif
                    </div>
                    @if ($meeting->ai_summary)
                        <div dir="auto" class="prose-chat text-sm text-slate-700">{!! \Illuminate\Support\Str::markdown($meeting->ai_summary, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @else
                        <x-empty class="py-6">
                            {{ $meeting->transcript ? __('No summary yet — generate one from the transcript.') : __('Add a transcript to generate an AI summary.') }}
                            @if ($canSummarize && ! $meeting->transcript)
                                <a href="{{ route('meetings.show', [$meeting, 'tab' => 'notes']) }}" class="mt-2 block font-medium text-brand">{{ __('Paste a transcript') }} →</a>
                            @endif
                        </x-empty>
                    @endif
                </div>
                @if ($meeting->key_points)
                    <div class="card p-5">
                        <h2 class="mb-3 font-semibold">{{ __('Key Points') }}</h2>
                        <ul class="space-y-2 text-sm">
                            @foreach ($meeting->key_points as $point)
                                <li dir="auto" class="flex gap-2"><span class="text-brand">●</span>{{ is_array($point) ? ($point['text'] ?? json_encode($point)) : $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{-- AI: meeting → tasks on the Kanban board --}}
                @if ($meeting->project && ($meeting->ai_summary || $meeting->key_points || $meeting->notes || $meeting->transcript))
                    @can('create', [\App\Models\Task::class, $meeting->project])
                        <div class="card flex flex-wrap items-center justify-between gap-3 border-violet-100 bg-linear-to-br from-violet-50/60 to-white p-5">
                            <div>
                                <h2 class="font-semibold">{{ __('Turn this meeting into tasks') }}</h2>
                                <p class="text-xs text-slate-500">{{ __('The AI pulls out the action items, owners and dates, and you review them before they go on the board.') }}</p>
                            </div>
                            @include('tasks._planner', ['project' => $meeting->project, 'suggestUrl' => route('planner.meeting', $meeting), 'meetingId' => $meeting->id, 'label' => __('Create tasks with AI')])
                        </div>
                    @endcan
                @endif
                @if ($meeting->agenda)
                    <div class="card p-5"><h2 class="mb-2 font-semibold">{{ __('Agenda') }}</h2><p dir="auto" class="whitespace-pre-line text-sm text-slate-600">{{ $meeting->agenda }}</p></div>
                @endif
            @elseif ($tab === 'notes')
                <form method="POST" action="{{ route('meetings.notes', $meeting) }}" class="card space-y-3 p-5">
                    @csrf @method('PUT')
                    <h2 class="font-semibold">{{ __('Shared Notes') }}</h2>
                    <textarea name="notes" rows="14" dir="auto" @disabled(! $canSummarize) class="input">{{ old('notes', $meeting->notes) }}</textarea>
                    @if ($canSummarize)<button class="btn-primary">{{ __('Save notes') }}</button>@endif
                </form>
                <form method="POST" action="{{ route('meetings.transcript', $meeting) }}" class="card space-y-3 p-5">
                    @csrf @method('PUT')
                    <div>
                        <h2 class="font-semibold">{{ __('Meeting transcript') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Paste the transcript exported from Google Meet, Zoom or Teams (one line per speaker). The AI summary and the task suggestions are built from it.') }}</p>
                    </div>
                    <textarea name="transcript" rows="10" dir="auto" @disabled(! $canSummarize) placeholder="[00:01] Lina: The Kanban screens are done.&#10;[00:45] Omar: I will review them on Thursday." class="input font-mono text-xs">{{ old('transcript', $meeting->transcript) }}</textarea>
                    @error('transcript')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    @if ($canSummarize)<button class="btn-primary">{{ __('Save transcript') }}</button>@endif
                </form>
            @else
                <div class="card p-5">
                    <div class="space-y-4">
                        @forelse ($meeting->comments as $c)
                            <div class="flex gap-3">
                                <x-avatar :user="$c->user" />
                                <div class="flex-1 rounded-2xl bg-slate-50 px-4 py-2.5">
                                    <div class="text-xs"><span class="font-semibold">{{ $c->user->name }}</span> <span class="text-slate-400">· {{ $c->created_at->diffForHumans() }}</span></div>
                                    <p dir="auto" class="mt-1 whitespace-pre-line text-sm">{{ $c->body }}</p>
                                </div>
                            </div>
                        @empty
                            <x-empty class="py-4">{{ __('No comments yet.') }}</x-empty>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('meetings.comments.store', $meeting) }}" class="mt-4 flex gap-2">
                        @csrf
                        <input name="body" required placeholder="{{ __('Write a comment…') }}" class="input rounded-full">
                        <button class="btn-primary">{{ __('Send') }}</button>
                    </form>
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            <div class="card flex max-h-[480px] flex-col p-5" x-data="{ q: '' }">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-semibold">{{ __('Transcript') }}</h2>
                    <select class="rounded-full border-0 bg-slate-100 px-3 py-1 text-xs"><option>{{ __('English') }}</option><option>العربية</option></select>
                </div>
                <input x-model="q" placeholder="{{ __('Search transcript') }}" class="input mb-3 rounded-full text-sm">
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto text-sm">
                    @forelse (preg_split('/\R+/', trim((string) $meeting->transcript), -1, PREG_SPLIT_NO_EMPTY) as $line)
                        <p dir="auto" x-show="!q || @js(mb_strtolower($line)).includes(q.toLowerCase())" class="rounded-lg px-2 py-1 hover:bg-slate-50">{{ $line }}</p>
                    @empty
                        <x-empty class="py-4">{{ __('No transcript.') }}</x-empty>
                    @endforelse
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-3 font-semibold">{{ __('Attendees') }}</h2>
                @foreach ($meeting->attendees as $a)
                    @php $resp = $a->pivot->response instanceof \BackedEnum ? $a->pivot->response->value : $a->pivot->response; @endphp
                    <div class="flex items-center gap-2 py-1.5 text-sm">
                        <x-avatar :user="$a" size="7" /> {{ $a->name }}
                        <span @class(['chip ms-auto', 'bg-emerald-50 text-emerald-600' => $resp === 'accepted', 'bg-rose-50 text-rose-600' => $resp === 'declined', 'bg-slate-100 text-slate-500' => $resp === 'pending'])>{{ __(ucfirst($resp)) }}</span>
                    </div>
                @endforeach
                <div class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-400">{{ __('Organized by :name', ['name' => $meeting->creator?->name]) }}</div>
            </div>

            <div class="card p-5">
                <h2 class="mb-3 font-semibold">{{ __('Attachments') }}</h2>
                @forelse ($meeting->attachments as $att)
                    <div class="flex items-center gap-2 py-1.5 text-sm">
                        <a href="{{ route('meetings.attachments.download', $att) }}" class="min-w-0 flex-1 truncate hover:text-brand"><x-icon name="paperclip" size="3.5" class="me-1 inline" />{{ $att->original_name }}</a>
                        @if ($att->uploaded_by === auth()->id() || $canEdit)
                            <form method="POST" action="{{ route('meetings.attachments.destroy', $att) }}">@csrf @method('DELETE')<button class="text-xs text-rose-500">✕</button></form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('None.') }}</p>
                @endforelse
                @if ($canSummarize)
                    <form method="POST" action="{{ route('meetings.attachments.store', $meeting) }}" enctype="multipart/form-data" class="mt-3 flex gap-2">
                        @csrf
                        <input type="file" name="file" required class="min-w-0 flex-1 text-xs">
                        <button class="btn-ghost px-3 text-xs">{{ __('Upload') }}</button>
                    </form>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
