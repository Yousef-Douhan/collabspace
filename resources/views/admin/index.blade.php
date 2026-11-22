@php
    $u = $stats['users'];
    $p = $stats['projects'];
    $t = $stats['tasks'];
    $size = $stats['files']['size_bytes'];
    $sizeLabel = $size >= 1048576 ? round($size / 1048576, 1).' MB' : ($size >= 1024 ? round($size / 1024, 1).' KB' : $size.' B');
    $roleChart = [
        'type' => 'doughnut',
        'data' => [
            'labels' => collect(\App\Enums\UserRole::cases())->map->label()->all(),
            'datasets' => [['data' => array_values($u['by_role']), 'backgroundColor' => ['#0B5FFF', '#8B5CF6', '#10B981'], 'borderWidth' => 0]],
        ],
        'options' => ['maintainAspectRatio' => false, 'cutout' => '70%', 'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]]],
    ];
@endphp
<x-layouts.app :title="__('Admin')">
    <x-page-header :title="__('Admin panel')">
        <x-slot:subtitle><p class="mt-1 text-sm text-slate-500">{{ __('Workspace-wide numbers and user management.') }}</p></x-slot:subtitle>
        <a href="{{ route('admin.users') }}" class="btn-primary">{{ __('Manage users') }}</a>
    </x-page-header>

    @include('admin._tabs')

    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Users" :value="$u['total']" icon="users" tone="brand"
                     :progress="$u['total'] ? $u['active'] / $u['total'] * 100 : 0" :progress-label="__(':n active', ['n' => $u['active']])" />
        <x-stat-card label="Projects" :value="$p['total']" icon="folder" tone="violet"
                     :progress="$p['total'] ? $p['active'] / $p['total'] * 100 : 0" :progress-label="__(':n active', ['n' => $p['active']])" />
        <x-stat-card label="Tasks" :value="$t['total']" icon="check-circle" tone="emerald"
                     :progress="$t['completion_rate']" :progress-label="$t['completion_rate'].'%'" />
        <x-stat-card label="Overdue" :value="$t['overdue']" icon="clock" tone="amber" />
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Users by role') }}</h2>
            <div class="h-56"><canvas x-data="chart(@js($roleChart))"></canvas></div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-center text-sm">
                <div class="rounded-xl bg-slate-50 p-3"><div class="text-lg font-semibold">{{ $u['new_this_month'] }}</div><div class="text-xs text-slate-500">{{ __('New this month') }}</div></div>
                <div class="rounded-xl bg-rose-50 p-3"><div class="text-lg font-semibold text-rose-600">{{ $u['deactivated'] }}</div><div class="text-xs text-slate-500">{{ __('Deactivated') }}</div></div>
            </div>
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Workspace activity') }}</h2>
            <dl class="divide-y divide-slate-50 text-sm">
                @foreach ([
                    ['Active projects', $p['active'], 'folder'],
                    ['Completed projects', $p['completed'], 'check-circle'],
                    ['Overdue projects', $p['overdue'], 'clock'],
                    ['Meetings this week', $stats['meetings_this_week'], 'calendar'],
                    ['Messages (7 days)', $stats['messages_last_7_days'], 'message'],
                    ['AI questions (7 days)', $stats['ai_questions_last_7_days'], 'sparkles'],
                    ['Files stored', $stats['files']['total'].' · '.$sizeLabel, 'file'],
                ] as [$label, $value, $icon])
                    <div class="flex items-center gap-3 py-2.5">
                        <span class="rounded-lg bg-slate-100 p-1.5 text-slate-500"><x-icon :name="$icon" /></span>
                        <dt class="flex-1 text-slate-600">{{ __($label) }}</dt>
                        <dd @class(['font-semibold', 'text-rose-600' => $label === 'Overdue projects' && $value > 0])>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Recent activity') }}</h2>
            <div class="max-h-96 space-y-3 overflow-y-auto pe-1">
                @forelse ($activity as $a)
                    <div class="flex gap-3 text-sm">
                        @if ($a->user)<x-avatar :user="$a->user" size="7" />@endif
                        <div class="min-w-0 flex-1">
                            <p><span class="font-medium">{{ $a->user?->name }}</span> <span class="text-slate-600">{{ $a->description }}</span></p>
                            <p class="text-[11px] text-slate-400">{{ $a->created_at->diffForHumans() }}@if ($a->project) · {{ $a->project->name }}@endif</p>
                        </div>
                    </div>
                @empty
                    <x-empty class="py-4">{{ __('No activity yet.') }}</x-empty>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
