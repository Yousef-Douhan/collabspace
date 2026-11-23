@php
    $done = $project->completed_tasks_count;
    $total = $project->tasks_count;
    $pct = $project->completion_percentage;
    $columns = ['todo' => ['To-do', 'bg-slate-100'], 'in_progress' => ['In Progress', 'bg-brand-50'], 'in_review' => ['In Review', 'bg-pink-50'], 'completed' => ['Completed', 'bg-emerald-50']];
    $daysTotal = $project->start_date && $project->end_date ? max(1, $project->start_date->diffInDays($project->end_date)) : null;
    $daysPassed = $daysTotal ? min($daysTotal, max(0, $project->start_date->diffInDays(now()))) : 0;
@endphp
<x-layouts.app :title="$project->name">
    <x-page-header :title="$project->name" :crumbs="['Projects' => route('projects.index')]">
        <x-slot:subtitle>
            <span class="mt-2 inline-block rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-600">{{ $pct }}% Completed</span>
        </x-slot:subtitle>
        @if ($daysTotal)
            <div class="card w-72 px-4 py-3">
                <div class="mb-1.5 text-xs font-medium">{{ __('Due Date') }}</div>
                <x-progress :value="$daysPassed / $daysTotal * 100" />
                <div class="mt-1.5 flex justify-between text-[11px] text-slate-500">
                    <span>{{ __('Start') }}: {{ $project->start_date->translatedFormat('M d') }}</span><span>{{ __('Deadline') }}: {{ $project->end_date->translatedFormat('M d') }}</span>
                </div>
            </div>
        @endif
    </x-page-header>

    <div class="mb-6 flex gap-6 border-b border-slate-200">
        @foreach (['overview' => 'Overview', 'tasks' => 'Tasks', 'teams' => 'Teams', 'files' => 'Files'] as $key => $label)
            <a href="{{ route('projects.show', [$project, 'tab' => $key]) }}" @class(['tab', 'tab-active' => $tab === $key])>{{ __($label) }}</a>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <div class="card p-5">
                    <h2 class="mb-2 font-semibold">{{ __('Description') }}</h2>
                    <p class="text-sm text-slate-600">{{ $project->description ?: 'No description yet.' }}</p>
                </div>

                <div class="card p-5" x-data="{ editing: false }">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="font-semibold">{{ __('Project Wiki') }}</h2>
                        <button @click="editing = !editing" class="text-xs text-brand" x-text="editing ? @js(__('Cancel')) : @js(__('Edit'))"></button>
                    </div>
                    <div x-show="!editing" class="prose-chat text-sm text-slate-700">{!! $project->wiki ? \Illuminate\Support\Str::markdown($project->wiki, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : '<p class="text-slate-400">'.e(__('The wiki is empty.')).'</p>' !!}</div>
                    <form x-cloak x-show="editing" method="POST" action="{{ route('projects.wiki', $project) }}" class="space-y-2">
                        @csrf @method('PUT')
                        <textarea name="wiki" rows="10" class="input font-mono text-xs">{{ $project->wiki }}</textarea>
                        <button class="btn-primary">{{ __('Save wiki') }}</button>
                    </form>
                </div>

                <div class="card p-5">
                    <h2 class="mb-4 font-semibold">{{ __('Product Details') }}</h2>
                    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <div><div class="text-xs text-slate-500">{{ __('Project Type') }}</div><div class="font-medium">{{ strtoupper($project->type ?? '—') }}</div></div>
                        <div><div class="text-xs text-slate-500">{{ __('Start Date') }}</div><div class="font-medium">{{ $project->start_date?->translatedFormat('M d') ?? '—' }}</div></div>
                        <div><div class="text-xs text-slate-500">{{ __('Deadline') }}</div><div class="font-medium">{{ $project->end_date?->translatedFormat('M d') ?? '—' }}</div></div>
                        <div><div class="text-xs text-slate-500">{{ __('Priority') }}</div><x-priority :value="$project->priority" /></div>
                        <div><div class="text-xs text-slate-500">{{ __('Total Tasks') }}</div><div class="font-medium">{{ $total }}</div></div>
                        <div><div class="text-xs text-slate-500">{{ __('Owner') }}</div><div class="font-medium">{{ $project->owner->name }}</div></div>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="card p-5 text-center">
                    <h2 class="mb-4 text-start font-semibold">{{ __('Project Progress') }}</h2>
                    <div class="relative mx-auto size-36">
                        <svg viewBox="0 0 36 36" class="size-full -rotate-90">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#EEF2F7" stroke-width="3"/>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#0B5FFF" stroke-width="3" stroke-linecap="round" stroke-dasharray="{{ $pct }} 100"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center"><span class="text-2xl font-semibold">{{ $pct }}%</span><span class="text-[11px] text-slate-500">{{ __('Completed') }}</span></div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 text-xs">
                        <div><div class="text-base font-semibold">{{ $byStatus->get('todo', collect())->count() }}</div><span class="text-amber-500">●</span> {{ __('Pending') }}</div>
                        <div><div class="text-base font-semibold">{{ $byStatus->get('in_progress', collect())->count() + $byStatus->get('in_review', collect())->count() }}</div><span class="text-brand">●</span> {{ __('In progress') }}</div>
                        <div><div class="text-base font-semibold">{{ $done }}</div><span class="text-emerald-500">●</span> {{ __('Completed') }}</div>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">{{ __('Team Members') }}</h2>
                        <a href="{{ route('projects.show', [$project, 'tab' => 'teams']) }}" class="text-xs text-brand">{{ __('Manage') }}</a>
                    </div>
                    @foreach ($project->members as $m)
                        <a href="{{ route('members.show', $m) }}" class="flex items-center gap-3 py-1.5">
                            <x-avatar :user="$m" size="8" />
                            <span class="text-sm">{{ $m->name }}</span>
                            @if (in_array($m->pivot->role, ['owner', 'manager']))<span class="chip bg-brand-50 text-brand">{{ __('Leader') }}</span>@endif
                            <span class="ms-auto text-[11px] text-slate-400">{{ $m->track?->label() }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @elseif ($tab === 'tasks')
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($columns as $key => [$label, $tone])
                <div class="card overflow-hidden">
                    <div class="{{ $tone }} px-4 py-3 text-sm font-medium">• {{ __($label) }} <span class="text-slate-400">{{ $byStatus->get($key, collect())->count() }}</span></div>
                    <div class="space-y-2 p-3">
                        @forelse ($byStatus->get($key, collect())->sortBy('position') as $t)
                            <a href="{{ route('tasks.show', $t) }}" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                                <div class="flex items-start justify-between gap-2 text-sm font-medium">{{ $t->title }} <x-priority :value="$t->priority" /></div>
                                <div class="mt-2 flex items-center justify-between"><x-avatars :users="$t->assignees" /><span class="text-xs text-slate-500">{{ $t->due_date?->translatedFormat('M d') }}</span></div>
                            </a>
                        @empty
                            <x-empty class="py-4">{{ __('Nothing here.') }}</x-empty>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4 text-end"><a href="{{ route('tasks.index', ['project_id' => $project->id]) }}" class="btn-ghost">{{ __('Open board ›') }}</a></div>
    @elseif ($tab === 'teams')
        <div class="card divide-y divide-slate-50">
            @foreach ($project->members as $m)
                <div class="flex items-center gap-4 px-5 py-3">
                    <x-avatar :user="$m" size="9" />
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('members.show', $m) }}" class="font-medium">{{ $m->name }}</a>
                        <div class="text-xs text-slate-500">{{ $m->job_title }} · {{ $m->track?->label() }}</div>
                    </div>
                    <span @class(['chip', 'bg-emerald-50 text-emerald-600' => $m->availability?->value === 'available', 'bg-amber-50 text-amber-600' => $m->availability?->value === 'busy', 'bg-slate-100 text-slate-500' => $m->availability?->value === 'away'])>{{ $m->availability?->label() }}</span>
                    <span class="chip bg-slate-100 text-slate-600">{{ ucfirst($m->pivot->role) }}</span>
                    @if ($canManage && $m->id !== $project->owner_id)
                        <form method="POST" action="{{ route('projects.members.destroy', [$project, $m]) }}" onsubmit="return confirm(@js(__('Remove :name from the project?', ['name' => $m->name])))">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-600">{{ __('Remove') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($canManage && $users->isNotEmpty())
            <form method="POST" action="{{ route('projects.members.store', $project) }}" class="card mt-4 flex flex-wrap items-end gap-3 p-5">
                @csrf
                <div class="min-w-56 flex-1"><label class="label">{{ __('Add member') }}</label>
                    <select name="user_id" class="input">@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Role') }}</label>
                    <select name="role" class="input"><option value="member">{{ __('Member') }}</option><option value="manager">{{ __('Manager') }}</option></select>
                </div>
                <button class="btn-primary">{{ __('Add') }}</button>
            </form>
        @endif
    @else
        <div class="mb-4 flex justify-end">
            <button class="btn-primary" x-data @click="$dispatch('open-modal', 'upload')">{{ __('Upload File +') }}</button>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($project->files as $f)
                @include('files._card', ['file' => $f])
            @empty
                <x-empty class="col-span-full">{{ __('No files in this project yet.') }}</x-empty>
            @endforelse
        </div>
        @include('files._upload', ['projects' => collect([$project]), 'selected' => $project->id])
    @endif
</x-layouts.app>
