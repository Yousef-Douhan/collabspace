<x-layouts.app :title="__('Projects')">
    <x-page-header :title="__('All Projects')">
        <button class="btn-primary" x-data @click="$dispatch('open-modal', 'new-project')">{{ __('Add Project +') }}</button>
    </x-page-header>

    <form class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search') }}" class="input max-w-xs rounded-full">
        <div class="flex gap-2">
            <select name="status" onchange="this.form.submit()" class="input w-auto rounded-full">
                <option value="">{{ __('All Projects') }}</option>
                @foreach (\App\Enums\ProjectStatus::cases() as $st)
                    <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
            <select name="sort" onchange="this.form.submit()" class="input w-auto rounded-full">
                <option value="newest">{{ __('Newest') }}</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Oldest') }}</option>
            </select>
        </div>
    </form>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-start text-xs text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Project Name') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Start Date') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Deadline') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Progress') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Files') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Teams') }}</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Priority') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($projects as $p)
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="location='{{ route('projects.show', $p) }}'">
                        <td class="px-5 py-3.5 font-medium">
                            <span class="me-2 inline-flex rounded-md bg-violet-50 p-1 align-middle text-violet-600"><x-icon name="folder" size="3.5" /></span>{{ $p->name }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $p->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $p->end_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span @class(['text-xs font-medium', 'text-rose-500' => $p->status->value === 'active', 'text-emerald-600' => $p->status->value === 'completed', 'text-slate-400' => $p->status->value === 'archived'])>{{ $p->status->label() }}</span>
                        </td>
                        <td class="w-36 px-5 py-3.5">
                            <div class="flex items-center gap-2"><x-progress :value="$p->completion_percentage" /><span class="text-xs text-slate-500">{{ $p->completion_percentage }}%</span></div>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><x-icon name="file" size="3.5" class="me-1 inline align-[-2px]" />{{ trans_choice(':count File|:count Files', $p->files_count) }}</td>
                        <td class="px-5 py-3.5"><x-avatars :users="$p->members" /></td>
                        <td class="px-5 py-3.5"><x-priority :value="$p->priority" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty>{{ __('No projects yet — create the first one.') }}</x-empty></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="new-project" :title="__('New Project')">
        <form method="POST" action="{{ route('projects.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="_modal" value="new-project">
            <div><label class="label">{{ __('Project name') }}</label><input name="name" value="{{ old('name') }}" required class="input"></div>
            <div><label class="label">{{ __('Description') }}</label><textarea name="description" rows="3" class="input">{{ old('description') }}</textarea></div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">{{ __('Priority') }}</label>
                    <select name="priority" class="input">@foreach (\App\Enums\Priority::cases() as $pr)<option value="{{ $pr->value }}" @selected(old('priority', 'medium') === $pr->value)>{{ $pr->label() }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="label">{{ __('Type') }}</label>
                    <select name="type" class="input">@foreach (['saas' => 'SaaS', 'mobile' => 'Mobile', 'website' => 'Website'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Start date') }}</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="input"></div>
                <div><label class="label">{{ __('Deadline') }}</label><input type="date" name="end_date" value="{{ old('end_date') }}" class="input"></div>
            </div>
            <div>
                <label class="label">{{ __('Team members') }}</label>
                <select name="members[]" multiple class="input h-28">
                    @foreach ($users as $u)@continue($u->id === auth()->id())<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <button class="btn-primary w-full">{{ __('Create project') }}</button>
        </form>
    </x-modal>
</x-layouts.app>
