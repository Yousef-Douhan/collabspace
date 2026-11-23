@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Theme before first paint (no white flash): saved choice, else the device setting --}}
    <script>(function(){try{var t=localStorage.getItem('cs-theme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d)}catch(e){}})();</script>
    <meta name="description" content="CollabSpace: an open-source team-collaboration demo app (Laravel) built for the Huma-volve CollabSpace Challenge.">
    @if (config('services.google.site_verification'))
        <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
    @endif
    <title>{{ $title ? $title.' · ' : '' }}Collabspace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (app()->getLocale() === 'ar')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700&display=swap" rel="stylesheet">
        <style>html[lang="ar"] body { font-family: 'Cairo', ui-sans-serif, system-ui, sans-serif; }</style>
    @endif
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <div class="flex items-center justify-between px-10 py-6">
        <span class="text-lg font-semibold">Collabspace</span>
        <div class="flex items-center gap-2">
        <button type="button" x-data="themeToggle" @click="toggle()" class="inline-flex size-8 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:border-brand hover:text-brand" title="{{ __('Dark / light mode') }}" aria-label="{{ __('Dark / light mode') }}">
            <svg class="theme-icon-moon size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            <svg class="theme-icon-sun size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>
        @php $languages = ['en' => 'English', 'ar' => 'العربية', 'fr' => 'Français']; @endphp
        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
            <button type="button" @click="open = !open" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-600 hover:border-brand hover:text-brand">{{ $languages[app()->getLocale()] ?? 'English' }} ▾</button>
            <div x-cloak x-show="open" class="absolute end-0 z-40 mt-2 w-36 rounded-2xl border border-slate-100 bg-white p-2 shadow-lg">
                @foreach ($languages as $code => $name)
                    <form method="POST" action="{{ route('locale.switch') }}">
                        @csrf
                        <button type="submit" name="locale" value="{{ $code }}" @class(['block w-full rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50', 'font-semibold text-brand' => app()->getLocale() === $code])>{{ $name }}</button>
                    </form>
                @endforeach
            </div>
        </div>
        </div>
    </div>
    <div class="flex min-h-[75vh] items-center justify-center px-4">
        <div class="rise w-full max-w-md rounded-3xl bg-white p-10 shadow-xl shadow-slate-200/60">
            {{ $slot }}
        </div>
    </div>
    {{-- Who runs this site: an open-source portfolio demo, not a real company's sign-in page --}}
    <p class="px-4 pb-8 text-center text-xs text-slate-400">
        {{ __('CollabSpace is an open-source demo project built by Yousef Douhan for the Huma-volve CollabSpace Challenge.') }}
        <a href="https://github.com/Yousef-Douhan/collabspace" class="text-brand hover:underline" target="_blank" rel="noopener">{{ __('Source code on GitHub') }}</a>
    </p>
</body>
</html>
