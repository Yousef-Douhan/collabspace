@props(['label', 'value', 'change' => null, 'suffix' => '%', 'icon' => null, 'tone' => 'brand', 'progress' => null, 'progressLabel' => null])
@php
    $tones = ['amber' => ['bg-amber-50 text-amber-500', 'bg-amber-400'], 'brand' => ['bg-brand-50 text-brand', 'bg-brand'], 'emerald' => ['bg-emerald-50 text-emerald-500', 'bg-emerald-500'], 'violet' => ['bg-violet-50 text-violet-500', 'bg-violet-500']];
    [$iconTone, $barTone] = $tones[$tone] ?? $tones['brand'];
@endphp
<div class="card p-5">
    <div class="flex items-start justify-between">
        <span class="text-sm font-medium text-slate-700">{{ __($label) }}</span>
        @if ($icon)<span class="{{ $iconTone }} rounded-lg p-1.5 text-xs">{{ $icon }}</span>@endif
    </div>
    <div class="mt-2 flex items-baseline gap-2">
        <span class="text-3xl font-semibold">{{ $value }}</span>
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
