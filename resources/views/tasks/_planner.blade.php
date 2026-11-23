{{--
    AI task planner: a button + review dialog.
    @include('tasks._planner', ['project' => $project, 'suggestUrl' => …, 'label' => …, 'meetingId' => null|int])
--}}
@php
    $plannerMembers = $project->members()->orderBy('users.name')->get(['users.id', 'users.name'])->map->only('id', 'name')->values();
@endphp
<div x-data="taskPlanner({
        suggestUrl: @js($suggestUrl),
        createUrl: @js(route('planner.create', $project)),
        meetingId: @js($meetingId ?? null),
    })" class="contents">
    <button type="button" @click="open()" class="inline-flex items-center gap-1.5 rounded-full bg-linear-to-r from-brand to-violet-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:shadow-md hover:shadow-brand/30 active:scale-95">
        <x-icon name="sparkles" /> {{ $label }}
    </button>

    <div x-cloak x-show="visible" x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="visible = false">
        <div @click.outside="if (!saving) visible = false" x-show="visible"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="flex items-center gap-2 text-lg font-semibold"><x-icon name="sparkles" class="text-violet-500" /> {{ $label }}</h3>
                <button type="button" @click="visible = false" class="rounded-full p-1 text-slate-400 hover:bg-slate-100" aria-label="{{ __('Close') }}">✕</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                {{-- Thinking --}}
                <div x-show="loading" class="flex flex-col items-center justify-center gap-3 py-16 text-slate-500">
                    <span class="float flex size-14 items-center justify-center rounded-2xl bg-violet-50 text-violet-500"><x-icon name="sparkles" size="7" /></span>
                    <span class="flex items-center gap-1 text-violet-500"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></span>
                    <span class="text-sm">{{ __('The AI is planning the tasks…') }}</span>
                </div>

                <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700"></p>

                {{-- Review & edit --}}
                <div x-show="!loading && tasks.length" class="space-y-3">
                    <p class="text-xs text-slate-500">{{ __('Review the suggestions: untick what you do not want, edit anything, then create them on the board.') }}</p>
                    <template x-for="(task, i) in tasks" :key="i">
                        <div class="msg-in rounded-2xl border p-4 transition" :class="task.keep ? 'border-violet-200 bg-violet-50/30' : 'border-slate-100 opacity-50'" :style="`animation-delay: ${i * 60}ms`">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" x-model="task.keep" class="mt-2.5 size-4 accent-violet-600">
                                <div class="min-w-0 flex-1 space-y-2">
                                    <input x-model="task.title" dir="auto" class="input font-medium">
                                    <textarea x-model="task.description" dir="auto" rows="2" class="input text-xs text-slate-600"></textarea>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        <select x-model="task.track" class="input text-xs">
                                            @foreach (\App\Enums\Track::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                                        </select>
                                        <select x-model="task.priority" class="input text-xs">
                                            @foreach (\App\Enums\Priority::cases() as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach
                                        </select>
                                        <select x-model.number="task.assignee_id" class="input text-xs">
                                            <option :value="null">{{ __('— None —') }}</option>
                                            @foreach ($plannerMembers as $m)<option value="{{ $m['id'] }}">{{ $m['name'] }}</option>@endforeach
                                        </select>
                                        <input type="date" x-model="task.due_date" class="input text-xs">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="!loading && tasks.length" class="flex items-center justify-between gap-3 border-t border-slate-100 px-6 py-4">
                <button type="button" @click="suggest()" class="btn-ghost text-xs">{{ __('Try again') }}</button>
                <button type="button" @click="create()" :disabled="saving || !kept" class="btn-primary">
                    <span x-show="!saving" x-text="@js(__('Create :n tasks')).replace(':n', kept)"></span>
                    <span x-cloak x-show="saving" class="inline-flex items-center gap-1"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>
