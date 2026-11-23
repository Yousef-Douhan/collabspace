@props(['title' => null])
@php
    $user = auth()->user();
    $nav = [
        ['Dashboard', 'dashboard', 'dashboard'],
        ['Projects', 'projects.index', 'projects.*'],
        ['Tasks', 'tasks.index', 'tasks.*'],
        ['Chats', 'chats.index', 'chats.*'],
        ['Meetings', 'meetings.index', 'meetings.*'],
        ['Reports', 'reports.index', 'reports.*'],
    ];
    if ($user->isAdmin()) {
        $nav[] = ['Admin', 'admin.index', 'admin.*'];
    }
    $unread = $user->unreadNotifications()->count();
    $latest = $user->notifications()->limit(6)->get();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="CollabSpace: an open-source team-collaboration demo app (Laravel) built for the Huma-volve CollabSpace Challenge.">
    @if (config('services.google.site_verification'))
        <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
    @endif
    @if (config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key'))
        <meta name="reverb" content="{{ json_encode(['key' => config('broadcasting.connections.reverb.key')] + config('broadcasting.client')) }}">
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
    {{-- Top bar (Figma: wordmark · pill nav · search · bell · user) --}}
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white print:hidden">
        <div class="mx-auto flex h-16 max-w-[1360px] items-center gap-6 px-6">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight">Collabspace</a>

            <nav class="flex items-center gap-1 rounded-full bg-slate-100 p-1">
                @foreach ($nav as [$label, $route, $pattern])
                    <a href="{{ route($route) }}"
                       @class([
                           'whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-medium transition',
                           'bg-brand text-white shadow-sm' => request()->routeIs($pattern),
                           'text-slate-600 hover:text-slate-900' => ! request()->routeIs($pattern),
                       ])>{{ __($label) }}</a>
                @endforeach
            </nav>

            {{-- Search with "Recent Searches" (Figma: Search Bar overlay). History is kept per browser. --}}
            <form action="{{ route('search') }}" class="relative ms-auto hidden w-64 lg:block"
                  x-data="recentSearches" @submit="remember($refs.q.value)" @click.outside="open = false" @keydown.escape="open = false">
                <div class="flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5">
                    <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input x-ref="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Search') }}" autocomplete="off" @focus="open = items.length > 0" class="w-full bg-transparent text-sm outline-none">
                </div>
                <div x-cloak x-show="open && items.length" x-transition.opacity
                     class="absolute inset-x-0 z-40 mt-2 rounded-2xl border border-slate-100 bg-white p-3 shadow-lg">
                    <div class="mb-1 flex items-center justify-between px-1">
                        <span class="text-xs font-medium text-slate-500">{{ __('Recent Searches') }}</span>
                        <button type="button" @click="clear()" class="text-[11px] text-slate-400 hover:text-rose-600">{{ __('Clear all') }}</button>
                    </div>
                    <template x-for="term in items" :key="term">
                        <div class="flex items-center justify-between rounded-lg px-1 hover:bg-slate-50">
                            <a :href="@js(route('search')) + '?q=' + encodeURIComponent(term)" @click="remember(term)" x-text="term" dir="auto" class="flex-1 truncate py-1.5 text-sm"></a>
                            <button type="button" @click="forget(term)" class="p-1 text-slate-400 hover:text-rose-600" :title="@js(__('Remove'))">✕</button>
                        </div>
                    </template>
                </div>
            </form>

            {{-- Real-time status: shown only while the Reverb WebSocket is connected --}}
            <span x-data="{ on: false }" x-init="window.onRealtimeState?.((v) => (on = v))" x-cloak x-show="on" x-transition.opacity
                  class="hidden items-center gap-1.5 whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700 lg:inline-flex" title="{{ __('Real-time updates are on') }}">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span></span>
                {{ __('Live') }}
            </span>

            {{-- Language menu (Figma: Language overlay) --}}
            @php $languages = ['en' => 'English', 'ar' => 'العربية', 'fr' => 'Français']; @endphp
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button type="button" @click="open = !open" class="whitespace-nowrap rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-600 hover:border-brand hover:text-brand">
                    {{ $languages[app()->getLocale()] ?? 'English' }} ▾
                </button>
                <div x-cloak x-show="open" x-transition.opacity class="absolute end-0 z-40 mt-2 w-40 rounded-2xl border border-slate-100 bg-white p-2 shadow-lg">
                    @foreach ($languages as $code => $name)
                        <form method="POST" action="{{ route('locale.switch') }}">
                            @csrf
                            <button type="submit" name="locale" value="{{ $code }}" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50">
                                {{ $name }} @if (app()->getLocale() === $code)<x-icon name="check-circle" class="text-emerald-500" />@endif
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

            {{-- Notifications dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="relative rounded-full p-2 hover:bg-slate-100" aria-label="Notifications">
                    <svg @class(["size-5", "bell-ring" => $unread]) fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    @if ($unread)
                        <span class="absolute end-1 top-1 flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-rose-500"></span></span>
                    @endif
                </button>
                <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top
                     class="absolute end-0 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <span class="text-sm font-semibold">{{ __('Notifications') }}</span>
                        @if ($unread)
                            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                                <button class="text-xs text-brand">{{ __('Mark all read') }}</button>
                            </form>
                        @endif
                    </div>
                    @forelse ($latest as $n)
                        <a href="{{ route('notifications.open', $n->id) }}" @class(['block border-b border-slate-50 px-4 py-3 text-sm hover:bg-slate-50', 'bg-brand-50/50' => ! $n->read_at])>
                            <div class="font-medium">{{ $n->data['title'] ?? '' }}</div>
                            <div class="text-xs text-slate-500">{{ $n->data['message'] ?? '' }}</div>
                            <div class="mt-1 text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</div>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-slate-400">{{ __('You\'re all caught up.') }}</p>
                    @endforelse
                    <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 text-center text-xs font-medium text-brand hover:bg-slate-50">{{ __('View all') }}</a>
                </div>
            </div>

            {{-- User menu --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-2.5 text-start">
                    <span class="hidden whitespace-nowrap text-end leading-tight lg:block">
                        <span class="block text-xs font-semibold">{{ $user->name }}</span>
                        <span class="block text-[11px] text-slate-500">{{ $user->track?->label() }}</span>
                    </span>
                    <x-avatar :user="$user" size="9" />
                </button>
                <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top
                     class="absolute end-0 mt-2 w-48 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-xl">
                    <a href="{{ route('members.show', $user) }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('My profile') }}</a>
                    <a href="{{ route('settings.edit') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Settings') }}</a>
                    @if ($user->isAdmin())
                        <a href="{{ route('admin.users') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Manage users') }}</a>
                    @endif
                    <a href="{{ route('files.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Files') }}</a>
                    <a href="{{ route('ai.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Ask AI') }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="block w-full px-4 py-2 text-start text-sm text-rose-600 hover:bg-slate-50">{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1360px] px-6 py-8">
        @if (session('status'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
                 class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Real-time toasts (e.g. "Sara moved a task") --}}
    <div x-data="{ toasts: [], push(text) { const id = Date.now() + Math.random(); this.toasts.push({ id, text }); setTimeout(() => this.toasts = this.toasts.filter((t) => t.id !== id), 3500); } }"
         @realtime-toast.window="push($event.detail)"
         class="pointer-events-none fixed bottom-5 end-5 z-50 flex flex-col items-end gap-2 print:hidden">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition class="toast-in rounded-xl bg-ink px-4 py-2.5 text-sm text-white shadow-lg"><span class="me-1">⚡</span><span x-text="t.text"></span></div>
        </template>
    </div>
</body>
</html>
