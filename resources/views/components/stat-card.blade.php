@props(['label', 'value', 'change' => null, 'suffix' => '%', 'icon' => null, 'tone' => 'brand', 'progress' => null, 'progressLabel' => null, 'gauge' => null])
@php
    $tones = ['amber' => ['bg-amber-50 text-amber-500', 'bg-amber-400'], 'brand' => ['bg-brand-50 text-brand', 'bg-brand'], 'emerald' => ['bg-emerald-50 text-emerald-500', 'bg-emerald-500'], 'violet' => ['bg-violet-50 text-violet-500', 'bg-violet-500']];
    [$iconTone, $barTone] = $tones[$tone] ?? $tones['brand'];
@endphp
<div class="card p-5">
    <div class="flex items-start justify-between">
        <span class="text-sm font-medium text-slate-700">{{ __($label) }}</span>
        @if (! is_null($gauge))
            {{-- Half-circle gauge (Figma "Completion Rate" card) --}}
            @php $g = max(0, min(100, (int) $gauge)); @endphp
            <svg viewBox="0 0 64 36" class="h-9 w-16 shrink-0" aria-hidden="true">
                <path d="M6 32a26 26 0 0 1 52 0" fill="none" stroke="#EEF2F7" stroke-width="6" stroke-linecap="round"/>
                <path d="M6 32a26 26 0 0 1 52 0" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $g }} 100" class="{{ explode(' ', $iconTone)[1] ?? 'text-brand' }}"/>
                <line x1="32" y1="32" x2="{{ 32 - 20 * cos(deg2rad(180 * $g / 100)) }}" y2="{{ 32 - 20 * sin(deg2rad(180 * $g / 100)) }}" stroke="#334155" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="32" cy="32" r="3" fill="#334155"/>
            </svg>
        @elseif ($icon)
            <span class="{{ $iconTone }} rounded-lg p-1.5"><x-icon :name="$icon" /></span>
        @endif
    </div>
    <div class="mt-2 flex items-baseline gap-2">
        <span class="text-3xl font-semibold tabular-nums" data-stat x-data x-countup>{{ $value }}</span>
        @if (! is_null($change))
            <span @class(['text-xs', 'text-emerald-600' => $change >= 0, 'text-rose-600' => $change < 0])>
                {{ $change >= 0 ? '↗ +' : '↘ ' }}<span dir="ltr">{{ $change }}{{ trim($suffix) === '%' ? '%' : ' '.__(trim($suffix)) }}</span> <span class="text-slate-400">{{ __('from last month') }}</span>
            </span>
        @endif
    </div>
    @if (! is_null($progress))
        <div class="mt-4 flex items-center gap-3">
            <x-progress :value="$progress" :color="$barTone" />
            @if ($progressLabel)<span class="shrink-0 text-[11px] text-slate-500">{{ $progressLabel }}</span>@endif
        </div>
    @endif
</div>
