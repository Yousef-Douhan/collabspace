<x-layouts.app :title="$team->name">
    <x-page-header :title="$team->name" :crumbs="['Teams' => route('teams.index')]">
        <x-slot:subtitle>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ $team->description ?: __('No description.') }}</p>
        </x-slot:subtitle>
        @if ($canManage)
            <button class="btn-ghost" x-data @click="$dispatch('open-modal', 'edit-team')">{{ __('Edit') }}</button>
            <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm(@js(__('Delete team :name? Its members stay in their projects.', ['name' => $team->name])))">
                @csrf @method('DELETE')
                <button class="btn-ghost text-rose-600">{{ __('Delete') }}</button>
            </form>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Members --}}
        <div class="lg:col-span-2">
            <div class="card divide-y divide-slate-50">
                <div class="flex items-center justify-between px-5 py-3">
                    <h3 class="font-semibold">{{ __('Members') }}</h3>
                    <span class="text-xs text-slate-500">{{ trans_choice(':count member|:count members', $team->members->count()) }}</span>
                </div>
                @foreach ($team->members as $m)
                    <div class="flex items-center gap-4 px-5 py-3">
                        <x-avatar :user="$m" size="9" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('members.show', $m) }}" class="font-medium">{{ $m->name }}</a>
                            <div class="text-xs text-slate-500">{{ $m->job_title }}</div>
                        </div>
                        @if ($canManage)
                            <form method="POST" action="{{ route('teams.members.update', [$team, $m]) }}">
                                @csrf @method('PATCH')
                                <select name="role" onchange="this.form.submit()" class="input w-auto py-1 text-xs" aria-label="{{ __('Role') }}">
                                    @foreach (App\Enums\TeamRole::cases() as $r)<option value="{{ $r->value }}" @selected($m->pivot->role === $r->value)>{{ $r->label() }}</option>@endforeach
                                </select>
                            </form>
                            <form method="POST" action="{{ route('teams.members.destroy', [$team, $m]) }}" onsubmit="return confirm(@js(__('Remove :name from the team?', ['name' => $m->name])))">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-600">{{ __('Remove') }}</button>
                            </form>
                        @else
                            <span @class(['chip', 'bg-brand-50 text-brand' => $m->pivot->role === 'lead', 'bg-slate-100 text-slate-600' => $m->pivot->role !== 'lead'])>{{ App\Enums\TeamRole::from($m->pivot->role)->label() }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
            @error('user')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror

            @if ($canManage && $users->isNotEmpty())
                <form method="POST" action="{{ route('teams.members.store', $team) }}" class="card mt-4 flex flex-wrap items-end gap-3 p-5">
                    @csrf
                    <div class="min-w-56 flex-1"><label class="label">{{ __('Add member') }}</label>
                        <select name="user_id" class="input">@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Role') }}</label>
                        <select name="role" class="input">@foreach (App\Enums\TeamRole::cases() as $r)<option value="{{ $r->value }}" @selected($r->value === 'member')>{{ $r->label() }}</option>@endforeach</select>
                    </div>
                    <button class="btn-primary">{{ __('Add') }}</button>
                </form>
            @endif
        </div>

        {{-- Projects --}}
        <div>
            <div class="card p-5">
                <h3 class="mb-3 font-semibold">{{ __('Projects') }}</h3>
                <div class="space-y-2">
                    @forelse ($team->projects as $p)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2">
                            <span class="inline-flex rounded-md bg-violet-50 p-1 text-violet-600"><x-icon name="folder" size="3.5" /></span>
                            <a href="{{ route('projects.show', [$p, 'tab' => 'teams']) }}" class="min-w-0 flex-1 truncate text-sm font-medium">{{ $p->name }}</a>
                            @can('manageMembers', $p)
                                <form method="POST" action="{{ route('teams.projects.destroy', [$team, $p]) }}" onsubmit="return confirm(@js(__('Unlink :team? Its members stay in the project.', ['team' => $team->name])))">
                                    @csrf @method('DELETE')
                                    <button class="text-slate-400 hover:text-rose-600" aria-label="{{ __('Remove') }}">×</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Not linked to any project yet.') }}</p>
                    @endforelse
                </div>

                @if ($projects->isNotEmpty())
                    <form method="POST" action="{{ route('teams.projects.store', $team) }}" class="mt-4 space-y-2">
                        @csrf
                        <label class="label">{{ __('Link to a project') }}</label>
                        <select name="project_id" class="input">@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                        <button class="btn-primary w-full">{{ __('Link team') }}</button>
                        <p class="text-xs text-slate-400">{{ __('All team members join the project as members.') }}</p>
                    </form>
                @endif
            </div>
            <p class="mt-3 px-1 text-xs text-slate-400">{{ __('Created by :name', ['name' => $team->creator?->name ?? '—']) }} · {{ $team->created_at?->diffForHumans() }}</p>
        </div>
    </div>

    @if ($canManage)
        <x-modal name="edit-team" :title="__('Edit team')">
            <form method="POST" action="{{ route('teams.update', $team) }}" class="space-y-3">
                @csrf @method('PUT')
                <input type="hidden" name="_modal" value="edit-team">
                <div><label class="label">{{ __('Team name') }}</label><input name="name" value="{{ old('name', $team->name) }}" required maxlength="80" class="input">
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="label">{{ __('Description') }}</label><textarea name="description" rows="3" maxlength="500" class="input">{{ old('description', $team->description) }}</textarea></div>
                <button class="btn-primary w-full">{{ __('Save') }}</button>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
