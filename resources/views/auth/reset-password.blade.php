<x-layouts.guest :title="__('Choose a new password')">
    <div class="text-center">
        <h1 class="text-2xl font-semibold">{{ __('Choose a new password') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('At least 8 characters.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required class="input">
            @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">{{ __('New password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required autofocus class="input">
            @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="input">
        </div>
        <button class="btn-primary w-full">{{ __('Reset password') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" class="font-medium text-brand">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.guest>
