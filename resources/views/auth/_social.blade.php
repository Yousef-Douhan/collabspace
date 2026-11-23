{{-- "Continue with GitHub / Google": only the providers that are configured are shown --}}
@php $providers = \App\Http\Controllers\Web\SocialAuthController::enabled(); @endphp
@if ($providers)
    <div class="mt-6 flex items-center gap-3 text-xs text-slate-400">
        <span class="h-px flex-1 bg-slate-200"></span>{{ __('or continue with') }}<span class="h-px flex-1 bg-slate-200"></span>
    </div>
    <div @class(['mt-4 grid gap-3', 'grid-cols-2' => count($providers) > 1])>
        @foreach ($providers as $key => $label)
            <a href="{{ route('social.redirect', $key) }}" class="btn-ghost w-full gap-2" aria-label="{{ __('Continue with :provider', ['provider' => $label]) }}">
                @if ($key === 'github')
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .5a11.5 11.5 0 0 0-3.64 22.41c.58.1.79-.25.79-.56v-2c-3.2.7-3.87-1.37-3.87-1.37-.53-1.33-1.28-1.69-1.28-1.69-1.05-.71.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.76 2.7 1.25 3.36.96.1-.75.4-1.25.73-1.54-2.55-.29-5.24-1.28-5.24-5.68 0-1.25.45-2.28 1.18-3.08-.12-.29-.51-1.46.11-3.04 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.77 0c2.2-1.49 3.16-1.18 3.16-1.18.63 1.58.24 2.75.12 3.04.74.8 1.18 1.83 1.18 3.08 0 4.41-2.69 5.38-5.26 5.67.41.36.78 1.06.78 2.14v3.17c0 .31.21.67.8.56A11.5 11.5 0 0 0 12 .5z"/></svg>
                @else
                    <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.27c0-.85-.08-1.67-.22-2.45H12v4.63h6.46a5.52 5.52 0 0 1-2.4 3.62v3h3.88c2.27-2.09 3.56-5.17 3.56-8.8z"/><path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.9l-3.88-3c-1.07.72-2.45 1.15-4.06 1.15-3.13 0-5.78-2.11-6.72-4.95H1.27v3.1A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.28 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.27a12 12 0 0 0 0 10.8l4.01-3.1z"/><path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.59 1.8l3.44-3.44A11.96 11.96 0 0 0 1.27 6.6l4.01 3.1C6.22 6.88 8.87 4.77 12 4.77z"/></svg>
                @endif
                {{ $label }}
            </a>
        @endforeach
    </div>
@endif
