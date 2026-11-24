<x-layouts.app :title="__('Teams')">
    <x-page-header :title="__('Teams')">
        <x-slot:subtitle>
            <p class="mt-1 text-sm text-slate-500">{{ __('Groups of people with a lead. Link a team to a project and all of its members join it.') }}</p>
        </x-slot:subtitle>
        @can('create', App\Models\Team::class)
            <button class="btn-primary" x-data @click="$dispatch('open-modal', 'new-team')">{{ __('New Team +') }}</button>
        @endcan
    </x-page-header>

    <form class="mb-4">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search') }}" class="input max-w-xs rounded-full">
    </form>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($teams as $team)
            <a href="{{ route('teams.show', $team) }}" class="card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-sm font-bold {{ $team->colorClasses() }}">{{ $team->initials() }}</span>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-semibold group-hover:text-brand">{{ $team->name }}</h3>
                        @php($leads = $team->members->filter(fn ($m) => $m->pivot->role === 'lead'))
                        <p class="truncate text-xs text-slate-500">{{ __('Lead') }}: {{ $leads->pluck('name')->implode(', ') ?: '—' }}</p>
                    </div>
                </div>
                <p class="mt-3 line-clamp-2 min-h-10 text-sm text-slate-600">{{ $team->description ?: __('No description.') }}</p>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                    <x-avatars :users="$team->members" :max="4" />
                    <div class="flex gap-2 text-xs text-slate-500">
                        <span class="chip bg-slate-100 text-slate-600">{{ trans_choice(':count member|:count members', $team->members_count) }}</span>
                        <span class="chip bg-brand-50 text-brand">{{ trans_choice(':count project|:count projects', $team->projects_count) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <x-empty class="col-span-full">{{ __('No teams yet.') }}</x-empty>
        @endforelse
    </div>

    @can('create', App\Models\Team::class)
        <x-modal name="new-team" :title="__('New Team')">
            <form method="POST" action="{{ route('teams.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_modal" value="new-team">
                <div><label class="label">{{ __('Team name') }}</label><input name="name" value="{{ old('name') }}" required maxlength="80" class="input">
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="label">{{ __('Description') }}</label><textarea name="description" rows="3" maxlength="500" class="input">{{ old('description') }}</textarea></div>
                <div>
                    <label class="label">{{ __('Members') }}</label>
                    <select name="members[]" multiple class="input h-32">
                        @foreach ($users as $u)@continue($u->id === auth()->id())<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">{{ __('You will be the team lead.') }}</p>
                </div>
                <button class="btn-primary w-full">{{ __('Create team') }}</button>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
