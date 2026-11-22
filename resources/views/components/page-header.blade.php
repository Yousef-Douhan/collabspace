@props(['title', 'crumbs' => []])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        @if ($crumbs)
            <nav class="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
                @foreach ($crumbs as $label => $url)
                    <a href="{{ $url }}" class="hover:text-slate-800">{{ __($label) }}</a><span>›</span>
                @endforeach
                <span class="font-medium text-slate-800">{{ $title }}</span>
            </nav>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        {{ $subtitle ?? '' }}
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
