@php
    $s = $d['stats'];
    $c = $d['changes'];
    $chart = [
        'type' => 'line',
        'data' => [
            'labels' => array_column($d['charts']['project_overview'], 'month'),
            'datasets' => [
                ['label' => __('Created'), 'data' => array_column($d['charts']['project_overview'], 'created'), 'borderColor' => '#0B5FFF', 'backgroundColor' => 'rgba(11,95,255,.08)', 'fill' => true, 'tension' => .4, 'pointRadius' => 0],
                ['label' => __('Completed'), 'data' => array_column($d['charts']['project_overview'], 'completed'), 'borderColor' => '#10B981', 'tension' => .4, 'pointRadius' => 0],
            ],
        ],
        'options' => ['plugins' => ['legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle', 'boxWidth' => 8, 'boxHeight' => 8]]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'maintainAspectRatio' => false],
    ];
@endphp
<x-layouts.app :title="__('Dashboard')">
    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Pending Tasks" :value="$s['todo']" :change="$c['todo']" icon="⏳" tone="amber"
                     :progress="$s['total'] ? $s['todo'] / $s['total'] * 100 : 0" :progress-label="$s['todo'].'/'.$s['total']" />
        <x-stat-card label="In Progress" :value="$s['in_progress']" :change="$c['in_progress']" icon="⚙" tone="brand"
                     :progress="$s['total'] ? $s['in_progress'] / $s['total'] * 100 : 0" :progress-label="$s['in_progress'].'/'.$s['total']" />
        <x-stat-card label="Completed" :value="$s['completed']" :change="$c['completed']" icon="✓" tone="emerald"
                     :progress="$s['total'] ? $s['completed'] / $s['total'] * 100 : 0" :progress-label="$s['completed'].'/'.$s['total']" />
        <x-stat-card label="Completion Rate" :value="$s['completion_rate'].'%'" :change="$c['completion_rate_points']" suffix=" pts" icon="◔" tone="violet" />
    </div>

    <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-4">
        {{-- Project overview chart --}}
        <div class="card p-5 xl:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Project overview') }}</h2>
                <form class="flex gap-2">
                    <select name="project_id" onchange="this.form.submit()" class="rounded-full border-0 bg-slate-100 px-3 py-1 text-xs">
                        <option value="">{{ __('All Projects') }}</option>
                        @foreach ($projects as $p)<option value="{{ $p->id }}" @selected($projectId === $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="h-56"><canvas x-data="chart(@js($chart))"></canvas></div>
        </div>

        {{-- Active projects --}}
        <div class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Active Projects') }}</h2>
                <a href="{{ route('projects.index') }}" class="text-xs text-brand">{{ __('View All') }}</a>
            </div>
            <div class="space-y-3">
                @forelse ($d['active_projects'] as $p)
                    <a href="{{ route('projects.show', $p['id']) }}" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-medium">{{ $p['name'] }}</span>
                            <span class="text-xs text-slate-500">{{ $p['completion_percentage'] }}%</span>
                        </div>
                        <x-progress :value="$p['completion_percentage']" color="bg-violet-500" />
                    </a>
                @empty
                    <x-empty>{{ __('No active projects.') }}</x-empty>
                @endforelse
            </div>
        </div>

        {{-- Recent files --}}
        <div class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Recent Files') }}</h2>
                <a href="{{ route('files.index') }}" class="text-xs text-brand">{{ __('View All') }}</a>
            </div>
            <div class="space-y-3">
                @forelse ($d['recent_files'] as $f)
                    <div class="flex items-center gap-3 text-sm">
                        @php
                            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                            $tone = match (true) {
                                $ext === 'pdf' => 'bg-rose-50 text-rose-600',
                                in_array($ext, ['doc', 'docx', 'txt', 'md']) => 'bg-brand-50 text-brand',
                                in_array($ext, ['xls', 'xlsx', 'csv']) => 'bg-emerald-50 text-emerald-600',
                                in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'fig']) => 'bg-violet-50 text-violet-600',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <span class="{{ $tone }} flex size-9 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold uppercase">{{ $ext ?: 'file' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ $f['name'] }}</div>
                            <div class="text-xs text-slate-500">{{ $f['project'] }} · {{ $f['size'] }}</div>
                        </div>
                        <a href="{{ route('files.download', $f['id']) }}" class="text-slate-400 hover:text-brand" title="{{ __('Download') }}">⤓</a>
                    </div>
                @empty
                    <x-empty>{{ __('No files yet.') }}</x-empty>
                @endforelse
            </div>
        </div>

        {{-- Tasks --}}
        <div class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Tasks') }}</h2>
                <a href="{{ route('tasks.index') }}" class="text-xs text-brand">{{ __('View All') }}</a>
            </div>
            <div class="max-h-96 space-y-3 overflow-y-auto pe-1">
                @forelse ($d['priority_tasks'] as $t)
                    <a href="{{ route('tasks.show', $t['id']) }}" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-medium">{{ $t['title'] }}</span>
                            <x-priority :value="$t['priority']" />
                        </div>
                        <div class="mt-1 flex justify-between text-xs text-slate-500">
                            <span @class(['text-rose-600' => $t['is_overdue']])>{{ __('due') }} {{ $t['due_date'] ? \Illuminate\Support\Carbon::parse($t['due_date'])->translatedFormat('M d') : '—' }}</span>
                            <span>{{ $t['progress'] }}%</span>
                        </div>
                        <x-progress class="mt-2" :value="$t['progress']" />
                    </a>
                @empty
                    <x-empty>{{ __('No open tasks 🎉') }}</x-empty>
                @endforelse
            </div>
        </div>

        {{-- Teams --}}
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Teams') }}</h2>
            <div class="space-y-3">
                @foreach ($d['team_status'] as $m)
                    <a href="{{ route('members.show', $m['id']) }}" class="flex items-center gap-3 hover:opacity-80">
                        <x-avatar :user="(object) ['id' => $m['id'], 'name' => $m['name'], 'avatar_url' => null]" size="8" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate font-medium">{{ $m['name'] }}</span>
                                <span class="text-xs text-slate-500">{{ $m['completed_tasks'] }}/{{ $m['tasks'] }}</span>
                            </div>
                            <x-progress class="mt-1" :value="$m['tasks'] ? $m['completed_tasks'] / $m['tasks'] * 100 : 0" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Upcoming meetings --}}
        <div class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('Upcoming meetings') }}</h2>
                <a href="{{ route('meetings.index') }}" class="text-xs text-brand">{{ __('View All') }}</a>
            </div>
            <div class="space-y-3">
                @forelse ($d['upcoming_meetings'] as $m)
                    <a href="{{ route('meetings.show', $m['id']) }}" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <div class="text-sm font-medium">{{ $m['title'] }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ \Illuminate\Support\Carbon::parse($m['starts_at'])->translatedFormat('D, M d · H:i') }} · {{ $m['project'] ?? __('General') }}
                        </div>
                    </a>
                @empty
                    <x-empty>{{ __('No upcoming meetings.') }}</x-empty>
                @endforelse
            </div>
        </div>

        {{-- Ask AI widget --}}
        <div class="card flex flex-col p-5">
            <h2 class="font-semibold">{{ __('Ask AI') }}</h2>
            <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                <span class="mb-2 text-3xl">🤖</span>
                <p class="text-sm text-slate-500">{{ __('ask me anything about…') }}</p>
            </div>
            <form method="POST" action="{{ route('ai.ask') }}" class="rounded-2xl bg-slate-50 p-3">
                @csrf
                <textarea name="message" rows="2" required dir="auto" placeholder="ما المهام المتأخرة؟" class="w-full resize-none bg-transparent text-sm outline-none"></textarea>
                <div class="text-end"><button class="btn-primary px-3 py-1 text-xs">{{ __('Send ➤') }}</button></div>
            </form>
        </div>
    </div>
</x-layouts.app>
