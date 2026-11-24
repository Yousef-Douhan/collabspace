<x-layouts.guest :title="__('Reset your password')">
    <div class="text-center">
        <h1 class="text-2xl font-semibold">{{ __('Reset your password') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __("Enter your email and we'll send you a link to choose a new password.") }}</p>
    </div>

    @if (session('status'))
        <p class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="input" placeholder="{{ __('Your Email') }}">
            @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">{{ __('Send reset link') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" class="font-medium text-brand">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.guest>
