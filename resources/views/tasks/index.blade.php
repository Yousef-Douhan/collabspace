@php
    $meta = [
        'todo' => ['To-do', 'bg-slate-100 text-slate-700'],
        'in_progress' => ['In Progress', 'bg-brand-50 text-brand-700'],
        'in_review' => ['In Review', 'bg-pink-50 text-pink-700'],
        'completed' => ['Completed', 'bg-emerald-50 text-emerald-700'],
    ];
    $selectedProject = $projects->firstWhere('id', (int) request('project_id')) ?? $projects->first();
@endphp
<x-layouts.app :title="__('Tasks')">
    <x-page-header :title="__('Tasks')">
        <form class="flex flex-wrap gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search tasks') }}" class="input w-48 rounded-full">
            <select name="project_id" onchange="this.form.submit()" class="input w-auto rounded-full">
                <option value="">{{ __('Project ▾ All') }}</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <select name="mine" onchange="this.form.submit()" class="input w-auto rounded-full">
                <option value="">{{ __('All Tasks') }}</option>
                <option value="1" @selected(request('mine'))>{{ __('My Task') }}</option>
            </select>
        </form>
    </x-page-header>

    <div x-data="kanban" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <p x-cloak x-show="error" x-text="error" class="col-span-full rounded-xl bg-rose-50 px-4 py-2 text-sm text-rose-700"></p>

        @foreach ($meta as $status => [$label, $tone])
            <section class="flex min-h-40 flex-col rounded-3xl bg-white/60 p-2">
                <header class="{{ $tone }} mb-2 flex items-center justify-between rounded-2xl px-4 py-2.5 text-sm font-semibold">
                    <span>• {{ __($label) }}</span>
                    <span class="rounded-full bg-white/70 px-2 text-xs">{{ $columns[$status]->count() }}</span>
                </header>

                <div data-column="{{ $status }}" class="flex min-h-24 flex-1 flex-col gap-2">
                    @foreach ($columns[$status] as $task)
                        <article data-task="{{ $task->id }}" class="card cursor-grab p-4 active:cursor-grabbing">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <a href="{{ route('tasks.show', $task) }}" class="text-sm font-semibold hover:text-brand">{{ $task->title }}</a>
                                <x-priority :value="$task->priority" />
                            </div>
                            <div class="mb-2 text-xs text-slate-500">{{ $task->project->name }} · {{ $task->track->label() }}</div>
                            <div class="mb-3 flex items-center gap-2"><x-progress :value="$task->progress" /><span class="text-[11px] text-slate-500">{{ $task->progress }}%</span></div>
                            @php $reviewer = $task->assignees->first(fn ($u) => $u->pivot->is_reviewer); @endphp
                            @if ($reviewer)
                                <div class="mb-2 text-[11px] text-slate-500">{{ __('Reviewer:') }} <span class="font-medium text-slate-700">{{ $reviewer->name }}</span></div>
                            @endif
                            <div class="flex items-center justify-between">
                                <x-avatars :users="$task->assignees->reject(fn ($u) => $u->pivot->is_reviewer)" />
                                <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                    <span title="{{ __('Comments') }}">💬 {{ $task->comments_count }}</span>
                                    <span @class(['text-rose-600 font-medium' => $task->isOverdue()])>📅 {{ $task->due_date?->translatedFormat('M d') ?? '—' }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <button type="button" x-data @click="$dispatch('open-modal', 'new-task'); $dispatch('task-status', '{{ $status }}')"
                        class="mt-2 rounded-2xl border-2 border-dashed border-slate-200 py-2.5 text-sm text-slate-500 hover:border-brand hover:text-brand">
                    {{ __('Add Task +') }}
                </button>
            </section>
        @endforeach
    </div>

    <x-modal name="new-task" :title="__('New Task')" width="max-w-xl">
        @if ($projects->isEmpty())
            <x-empty>{{ __('Create a project first.') }}</x-empty>
        @else
            <form method="POST" action="{{ route('tasks.store') }}" class="space-y-3"
                  x-data="{ status: '{{ old('status', 'todo') }}', project: '{{ old('project_id', $selectedProject?->id) }}', members: @js($projects->mapWithKeys(fn ($p) => [$p->id => $p->members->map->only('id', 'name')->values()])) }"
                  @task-status.window="status = $event.detail">
                @csrf
                <input type="hidden" name="_modal" value="new-task">
                <input type="hidden" name="status" :value="status">
                <div><label class="label">{{ __('Title') }}</label><input name="title" value="{{ old('title') }}" required class="input"></div>
                <div><label class="label">{{ __('Description') }}</label><textarea name="description" rows="2" class="input">{{ old('description') }}</textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">{{ __('Project') }}</label>
                        <select name="project_id" x-model="project" class="input">@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Track') }}</label>
                        <select name="track" class="input">@foreach (\App\Enums\Track::cases() as $t)<option value="{{ $t->value }}" @selected(old('track') === $t->value)>{{ $t->label() }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Priority') }}</label>
                        <select name="priority" class="input">@foreach (\App\Enums\Priority::cases() as $pr)<option value="{{ $pr->value }}" @selected(old('priority', 'medium') === $pr->value)>{{ $pr->label() }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Estimated hours') }}</label><input type="number" min="0" name="estimated_hours" value="{{ old('estimated_hours') }}" class="input"></div>
                    <div><label class="label">{{ __('Start date') }}</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="input"></div>
                    <div><label class="label">{{ __('Due date') }}</label><input type="date" name="due_date" value="{{ old('due_date') }}" class="input"></div>
                    <div><label class="label">{{ __('Assignees') }}</label>
                        <select name="assignees[]" multiple class="input h-24">
                            <template x-for="m in members[project] ?? []" :key="m.id"><option :value="m.id" x-text="m.name"></option></template>
                        </select>
                    </div>
                    <div><label class="label">{{ __('Reviewer') }}</label>
                        <select name="reviewer_id" class="input">
                            <option value="">{{ __('— None —') }}</option>
                            <template x-for="m in members[project] ?? []" :key="m.id"><option :value="m.id" x-text="m.name"></option></template>
                        </select>
                    </div>
                </div>
                <button class="btn-primary w-full">{{ __('Create task') }}</button>
            </form>
        @endif
    </x-modal>
</x-layouts.app>
