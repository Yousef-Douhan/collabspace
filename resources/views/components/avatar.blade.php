@props(['user', 'size' => '8'])
@php
    $palette = ['bg-violet-100 text-violet-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700'];
    $color = $palette[($user->id ?? 0) % count($palette)];
    $initials = collect(explode(' ', (string) $user->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $sizes = ['6' => 'size-6 text-[10px]', '7' => 'size-7 text-[11px]', '8' => 'size-8 text-xs', '9' => 'size-9 text-xs', '12' => 'size-12 text-sm', '16' => 'size-16 text-lg'];
@endphp
@if ($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" title="{{ $user->name }}" {{ $attributes->class([$sizes[$size] ?? $sizes['8'], 'rounded-full object-cover ring-2 ring-white']) }}>
@else
    <span title="{{ $user->name }}" {{ $attributes->class([$sizes[$size] ?? $sizes['8'], $color, 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold ring-2 ring-white']) }}>{{ $initials }}</span>
@endif
