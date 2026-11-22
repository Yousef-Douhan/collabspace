@php
    $sections = [
        'projects' => ['Projects', fn ($p) => [route('projects.show', $p), $p->name, $p->status->label()]],
        'tasks' => ['Tasks', fn ($t) => [route('tasks.show', $t), $t->title, $t->project->name.' · '.$t->status->label()]],
        'files' => ['Files', fn ($f) => [route('files.download', $f), $f->original_name, $f->project->name]],
        'meetings' => ['Meetings', fn ($m) => [route('meetings.show', $m), $m->title, $m->starts_at->translatedFormat('M d, H:i')]],
        'people' => ['People', fn ($u) => [route('members.show', $u), $u->name, $u->job_title]],
    ];
    $total = collect($results)->sum(fn ($c) => $c->count());
@endphp
<x-layouts.app :title="__('Search')">
    <x-page-header :title="__('Search')" />
    <form class="mb-6"><input name="q" value="{{ $q }}" autofocus placeholder="{{ __('Search projects, tasks, files, meetings, people…') }}" class="input max-w-xl rounded-full"></form>

    @if (mb_strlen($q) < 2)
        <x-empty>{{ __('Type at least 2 characters.') }}</x-empty>
    @elseif ($total === 0)
        <x-empty>{{ __('No results for “:q”.', ['q' => $q]) }}</x-empty>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($sections as $key => [$label, $row])
                @continue($results[$key]->isEmpty())
                <div class="card p-5">
                    <h2 class="mb-2 font-semibold">{{ __($label) }} <span class="text-slate-400">{{ $results[$key]->count() }}</span></h2>
                    @foreach ($results[$key] as $item)
                        @php [$url, $title, $sub] = $row($item); @endphp
                        <a href="{{ $url }}" class="block rounded-xl px-3 py-2 hover:bg-slate-50">
                            <div class="text-sm font-medium">{{ $title }}</div>
                            <div class="text-xs text-slate-500">{{ $sub }}</div>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
