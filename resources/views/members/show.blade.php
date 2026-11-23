<x-layouts.app :title="$member->name">
    <div class="card mb-5 flex flex-wrap items-center gap-5 p-6">
        <x-avatar :user="$member" size="16" />
        <div class="flex-1">
            <h1 class="text-xl font-semibold">{{ $member->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-3 text-sm text-slate-500">
                <span>{{ $member->job_title }} · {{ $member->track?->label() }}</span>
                <span class="inline-flex items-center gap-1"><x-icon name="calendar" size="3.5" />{{ __('Joined :date', ['date' => ($member->joined_at ?? $member->created_at)->translatedFormat('M Y')]) }}</span>
            </div>
            <div class="mt-1 text-xs text-slate-400">{{ $member->email }}@if ($member->phone) · {{ $member->phone }}@endif</div>
        </div>
        <span @class(['chip', 'bg-emerald-50 text-emerald-600' => $member->availability?->value === 'available', 'bg-amber-50 text-amber-600' => $member->availability?->value === 'busy', 'bg-slate-100 text-slate-500' => $member->availability?->value === 'away'])>{{ $member->availability?->label() }}</span>
        @if ($member->id === auth()->id())
            <a href="{{ route('settings.edit') }}" class="btn-ghost">{{ __('Edit profile') }}</a>
        @endif
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-stat-card label="Completed Tasks" :value="$completed" icon="check-circle" tone="emerald" :progress="$tasks->count() ? $completed / $tasks->count() * 100 : 0" :progress-label="$completed.'/'.$tasks->count()" />
        <x-stat-card label="Hours Worked" :value="$hours.' h'" icon="clock" tone="brand" />
        <x-stat-card label="Projects" :value="$projects->count()" icon="folder" tone="violet" />
    </div>

    <div class="mb-5 flex gap-6 border-b border-slate-200">
        @foreach (['overview' => 'Overview', 'tasks' => 'Tasks', 'files' => 'Files'] as $key => $label)
            <a href="{{ route('members.show', [$member, 'tab' => $key]) }}" @class(['tab', 'tab-active' => $tab === $key])>{{ __($label) }}</a>
        @endforeach
        @if ($member->id === auth()->id())<a href="{{ route('settings.edit') }}" class="tab">{{ __('Settings') }}</a>@endif
    </div>

    @if ($tab === 'overview')
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div class="card p-5">
                <h2 class="mb-2 font-semibold">{{ __('About') }}</h2>
                <p dir="auto" class="whitespace-pre-line text-sm text-slate-600">{{ $member->about ?: 'Nothing here yet.' }}</p>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><div class="text-xs text-slate-500">{{ __('Experience') }}</div><div class="font-medium">{{ trans_choice(':count year|:count years', $member->years_of_experience ?? 0) }}</div></div>
                    <div><div class="text-xs text-slate-500">{{ __('Projects') }}</div><div class="font-medium">{{ $projects->pluck('name')->implode(', ') ?: '—' }}</div></div>
                </div>
            </div>
            <div class="card p-5">
                <h2 class="mb-3 font-semibold">{{ __('Recent Activities') }}</h2>
                @forelse ($activity as $a)
                    <div class="flex gap-3 py-1.5 text-sm">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand"></span>
                        <div class="flex-1">{{ $member->name }} {{ $a->description }}</div>
                        <span class="shrink-0 text-xs text-slate-400">{{ $a->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <x-empty class="py-4">{{ __('No activity yet.') }}</x-empty>
                @endforelse
            </div>
        </div>
    @elseif ($tab === 'tasks')
        <div class="card divide-y divide-slate-50">
            @forelse ($tasks as $t)
                <a href="{{ route('tasks.show', $t) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50">
                    <div class="min-w-0 flex-1"><div class="text-sm font-medium">{{ $t->title }}</div><div class="text-xs text-slate-500">{{ $t->project->name }}</div></div>
                    <span class="chip bg-slate-100 text-slate-600">{{ $t->status->label() }}</span>
                    <x-priority :value="$t->priority" />
                    <span class="w-16 text-end text-xs text-slate-500">{{ $t->due_date?->translatedFormat('M d') }}</span>
                </a>
            @empty
                <x-empty>{{ __('No tasks.') }}</x-empty>
            @endforelse
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($files as $file)
                @include('files._card')
            @empty
                <x-empty class="col-span-full">{{ __('No files.') }}</x-empty>
            @endforelse
        </div>
    @endif
</x-layouts.app>
