<x-layouts.app :title="$task->title">
    <x-page-header :title="$task->title" :crumbs="['Tasks' => route('tasks.index'), $task->project->name => route('projects.show', $task->project)]">
        @if ($canEdit)
            <button class="btn-ghost" x-data @click="$dispatch('open-modal', 'edit-task')">{{ __('Edit') }}</button>
        @endif
        @can('delete', $task)
            <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm(@js(__('Delete this task?')))">
                @csrf @method('DELETE')<button class="btn-danger">{{ __('Delete') }}</button>
            </form>
        @endcan
    </x-page-header>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="card p-5">
                <h2 class="mb-2 font-semibold">{{ __('Description') }}</h2>
                <p class="whitespace-pre-line text-sm text-slate-600">{{ $task->description ?: 'No description.' }}</p>
            </div>

            <div class="card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('Attachments') }}</h2>
                    @can('create', [\App\Models\ProjectFile::class, $task->project])
                        <button class="text-xs text-brand" x-data @click="$dispatch('open-modal', 'upload')">{{ __('Upload +') }}</button>
                    @endcan
                </div>
                <div class="space-y-2">
                    @forelse ($task->files as $file)
                        @include('files._card')
                    @empty
                        <x-empty class="py-4">{{ __('No attachments.') }}</x-empty>
                    @endforelse
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-4 font-semibold">{{ __('Comments') }} <span class="text-slate-400">{{ $task->comments->count() }}</span></h2>
                <div class="space-y-4">
                    @forelse ($task->comments as $c)
                        <div class="flex gap-3">
                            <x-avatar :user="$c->user" />
                            <div class="flex-1 rounded-2xl bg-slate-50 px-4 py-2.5">
                                <div class="text-xs"><span class="font-semibold">{{ $c->user->name }}</span> <span class="text-slate-400">· {{ $c->created_at->diffForHumans() }}</span></div>
                                <p class="mt-1 whitespace-pre-line text-sm">{{ $c->body }}</p>
                            </div>
                        </div>
                    @empty
                        <x-empty class="py-4">{{ __('No comments yet.') }}</x-empty>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="mt-4 flex gap-2">
                    @csrf
                    <input name="body" required placeholder="{{ __('Write a comment…') }}" class="input rounded-full">
                    <button class="btn-primary">{{ __('Send') }}</button>
                </form>
            </div>
        </div>

        <aside class="card h-fit space-y-4 p-5 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Status') }}</span><span class="chip bg-brand-50 text-brand">{{ $task->status->label() }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Priority') }}</span><x-priority :value="$task->priority" /></div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Track') }}</span><span>{{ $task->track->label() }}</span></div>
            <div>
                <div class="mb-1 flex justify-between"><span class="text-slate-500">{{ __('Progress') }}</span><span>{{ $task->progress }}%</span></div>
                <x-progress :value="$task->progress" />
            </div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Start') }}</span><span>{{ $task->start_date?->translatedFormat('M d, Y') ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Due') }}</span><span @class(['font-medium text-rose-600' => $task->isOverdue()])>{{ $task->due_date?->translatedFormat('M d, Y') ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Estimate') }}</span><span>{{ $task->estimated_hours ? $task->estimated_hours.' h' : '—' }}</span></div>
            <div>
                <div class="mb-2 text-slate-500">{{ __('Assignees') }}</div>
                @foreach ($task->assignees->reject(fn ($u) => $u->pivot->is_reviewer) as $u)
                    <div class="mb-1.5 flex items-center gap-2"><x-avatar :user="$u" size="7" />{{ $u->name }}</div>
                @endforeach
            </div>
            @if ($reviewer)
                <div><div class="mb-2 text-slate-500">{{ __('Reviewer') }}</div><div class="flex items-center gap-2"><x-avatar :user="$reviewer" size="7" />{{ $reviewer->name }}</div></div>
            @endif
            <div class="border-t border-slate-100 pt-3 text-xs text-slate-400">{{ __('Created by :name', ['name' => $task->creator?->name]) }} · {{ $task->created_at->diffForHumans() }}</div>
        </aside>
    </div>

    @if ($canEdit)
        <x-modal name="edit-task" :title="__('Edit Task')" width="max-w-xl">
            <form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-3">
                @csrf @method('PUT')
                <input type="hidden" name="_modal" value="edit-task">
                <div><label class="label">{{ __('Title') }}</label><input name="title" value="{{ old('title', $task->title) }}" required class="input"></div>
                <div><label class="label">{{ __('Description') }}</label><textarea name="description" rows="3" class="input">{{ old('description', $task->description) }}</textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">{{ __('Priority') }}</label>
                        <select name="priority" class="input">@foreach (\App\Enums\Priority::cases() as $pr)<option value="{{ $pr->value }}" @selected($task->priority === $pr)>{{ $pr->label() }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Track') }}</label>
                        <select name="track" class="input">@foreach (\App\Enums\Track::cases() as $t)<option value="{{ $t->value }}" @selected($task->track === $t)>{{ $t->label() }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Progress %') }}</label><input type="number" min="0" max="100" name="progress" value="{{ old('progress', $task->progress) }}" class="input"></div>
                    <div><label class="label">{{ __('Estimated hours') }}</label><input type="number" min="0" name="estimated_hours" value="{{ old('estimated_hours', $task->estimated_hours) }}" class="input"></div>
                    <div><label class="label">{{ __('Start date') }}</label><input type="date" name="start_date" value="{{ old('start_date', $task->start_date?->format('Y-m-d')) }}" class="input"></div>
                    <div><label class="label">{{ __('Due date') }}</label><input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}" class="input"></div>
                    <div><label class="label">{{ __('Assignees') }}</label>
                        <select name="assignees[]" multiple class="input h-24">
                            @foreach ($task->project->members as $m)<option value="{{ $m->id }}" @selected($task->assignees->contains('id', $m->id) && ! ($reviewer?->id === $m->id))>{{ $m->name }}</option>@endforeach
                        </select>
                    </div>
                    <div><label class="label">{{ __('Reviewer') }}</label>
                        <select name="reviewer_id" class="input">
                            <option value="">{{ __('— None —') }}</option>
                            @foreach ($task->project->members as $m)<option value="{{ $m->id }}" @selected($reviewer?->id === $m->id)>{{ $m->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <button class="btn-primary w-full">{{ __('Save') }}</button>
            </form>
        </x-modal>
    @endif

    @include('files._upload', ['projects' => collect([$task->project]), 'selected' => $task->project_id, 'taskId' => $task->id])
</x-layouts.app>
