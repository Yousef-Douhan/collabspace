@props(['value' => 0, 'color' => 'bg-brand'])
<div {{ $attributes->class('h-1.5 w-full overflow-hidden rounded-full bg-slate-100') }}>
    <div class="{{ $color }} bar-grow h-full rounded-full" style="width: {{ max(0, min(100, (int) $value)) }}%"></div>
</div>
