@php
    $k = $o['kpis'];
    $ch = $o['charts'];
    $line = fn ($labels, $data, $label, $color) => [
        'type' => 'line',
        'data' => ['labels' => $labels, 'datasets' => [['label' => $label, 'data' => $data, 'borderColor' => $color, 'backgroundColor' => $color.'22', 'fill' => true, 'tension' => 0.4, 'pointRadius' => 3]]],
        'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#EEF2F7']]]],
    ];
    $deadline = [
        'type' => 'bar',
        'data' => [
            'labels' => array_column($ch['completion_vs_deadline'], 'month'),
            'datasets' => [
                ['label' => __('Completed'), 'data' => array_column($ch['completion_vs_deadline'], 'completed'), 'backgroundColor' => '#C7D7FE', 'borderRadius' => 6],
                ['label' => __('On time'), 'data' => array_column($ch['completion_vs_deadline'], 'on_time'), 'backgroundColor' => '#0B5FFF', 'borderRadius' => 6],
            ],
        ],
        'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 10]]], 'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]],
    ];
@endphp
<x-layouts.app :title="__('Reports')">
    <x-page-header :title="__('Reports')">
        <form class="flex flex-wrap items-center gap-2">
            <select name="project_id" class="input w-auto rounded-full">
                <option value="">{{ __('All projects') }}</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="input w-auto rounded-full">
            <input type="date" name="to" value="{{ request('to') }}" class="input w-auto rounded-full">
            <button class="btn-ghost">{{ __('Apply') }}</button>
        </form>
    </x-page-header>

    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-stat-card label="Task Completion Rate" :value="$k['task_completion_rate']['value'].'%'" :change="$k['task_completion_rate']['change_points']" suffix=" pts" icon="check-circle" tone="emerald" />
        <x-stat-card label="Project Completion Rate" :value="$k['project_completion_rate']['value'].'%'" icon="folder" tone="brand" />
        <x-stat-card label="Team Activity Rate" :value="$k['team_activity_rate']['value'].'%'" :change="$k['team_activity_rate']['change_points']" suffix=" pts" icon="zap" tone="violet" />
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Task Completion Time vs Deadline') }}</h2>
            <div class="h-56"><canvas x-data="chart(@js($deadline))"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Projects Progress') }}</h2>
            <div class="h-56"><canvas x-data="chart(@js($line(array_column($ch['projects_progress'], 'month'), array_column($ch['projects_progress'], 'progress'), __('Progress %'), '#0B5FFF')))"></canvas></div>
        </div>
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Worker Activity Rate') }}</h2>
            <div class="h-56"><canvas x-data="chart(@js($line(array_column($ch['worker_activity'], 'month'), array_column($ch['worker_activity'], 'actions'), __('Actions'), '#8B5CF6')))"></canvas></div>
        </div>
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <h2 class="px-5 pt-5 font-semibold">{{ __('Project Progress') }}</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-xs text-slate-500"><tr><th class="px-5 py-2 text-start font-medium">{{ __('Project') }}</th><th class="px-3 text-start font-medium">{{ __('Tasks') }}</th><th class="px-3 text-start font-medium">{{ __('Overdue') }}</th><th class="w-40 px-5 text-start font-medium">{{ __('Completion') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($o['project_progress'] as $row)
                        <tr><td class="px-5 py-2.5 font-medium">{{ $row['project'] }}</td><td class="px-3">{{ $row['completed'] }}/{{ $row['tasks'] }}</td>
                            <td @class(['px-3', 'text-rose-600' => $row['overdue']])>{{ $row['overdue'] }}</td>
                            <td class="px-5"><div class="flex items-center gap-2"><x-progress :value="$row['completion_rate']" /><span class="text-xs">{{ $row['completion_rate'] }}%</span></div></td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty>{{ __('No data.') }}</x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-hidden">
            <h2 class="px-5 pt-5 font-semibold">{{ __('Team Performance') }}</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-xs text-slate-500"><tr><th class="px-5 py-2 text-start font-medium">{{ __('Member') }}</th><th class="px-3 text-start font-medium">{{ __('Assigned') }}</th><th class="px-3 text-start font-medium">{{ __('Overdue') }}</th><th class="w-40 px-5 text-start font-medium">{{ __('Completion') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($o['team_performance'] as $row)
                        <tr><td class="px-5 py-2.5"><a href="{{ route('members.show', $row['user_id']) }}" class="font-medium hover:text-brand">{{ $row['name'] }}</a></td><td class="px-3">{{ $row['completed'] }}/{{ $row['assigned'] }}</td>
                            <td @class(['px-3', 'text-rose-600' => $row['overdue']])>{{ $row['overdue'] }}</td>
                            <td class="px-5"><div class="flex items-center gap-2"><x-progress :value="$row['completion_rate']" color="bg-emerald-500" /><span class="text-xs">{{ $row['completion_rate'] }}%</span></div></td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty>{{ __('No data.') }}</x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('reports.store') }}" class="card space-y-3 p-5">
            @csrf
            <h2 class="font-semibold">{{ __('Submit a detailed report') }}</h2>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">{{ __('Report type') }}</label>
                    <select name="type" class="input">@foreach (\App\Enums\ReportType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Project') }}</label>
                    <select name="project_id" class="input"><option value="">{{ __('All projects') }}</option>@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('From') }}</label><input type="date" name="period_start" class="input"></div>
                <div><label class="label">{{ __('To') }}</label><input type="date" name="period_end" class="input"></div>
            </div>
            <div><label class="label">{{ __('Notes') }}</label><textarea name="notes" rows="3" dir="auto" class="input" placeholder="{{ __('Anything the reader should know…') }}"></textarea></div>
            <button class="btn-primary">{{ __('Submit report') }}</button>
        </form>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Saved reports') }}</h2>
            @forelse ($saved as $r)
                <div class="flex items-center gap-3 border-b border-slate-50 py-2.5 text-sm last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $r->type->label() }} <span class="text-slate-400">· {{ $r->project?->name ?? 'All projects' }}</span></div>
                        <div class="text-xs text-slate-500">{{ $r->created_at->translatedFormat('M d, Y H:i') }}</div>
                    </div>
                    <a href="{{ route('reports.export', $r) }}" class="text-xs text-brand">CSV</a>
                    @can('delete', $r)
                        <form method="POST" action="{{ route('reports.destroy', $r) }}">@csrf @method('DELETE')<button class="text-xs text-rose-500">{{ __('Delete') }}</button></form>
                    @endcan
                </div>
            @empty
                <x-empty class="py-4">{{ __('No saved reports yet.') }}</x-empty>
            @endforelse
        </div>
    </div>
</x-layouts.app>
