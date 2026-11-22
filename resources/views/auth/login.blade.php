<x-layouts.guest :title="__('Sign in')">
    <div class="text-center">
        <h1 class="text-2xl font-semibold">{{ __('Sign In') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Welcome back, you\'ve been missed!') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', 'admin@collabspace.test') }}" required autofocus class="input" placeholder="{{ __('Your Email') }}">
            @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required class="input" placeholder="{{ __('Password') }}">
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-slate-600"><input type="checkbox" name="remember" class="rounded border-slate-300 text-brand"> {{ __('Remember Me') }}</label>
        </div>
        <button class="btn-primary w-full">{{ __('Log in') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        {{ __("Don't have an account yet?") }} <a href="{{ route('register') }}" class="font-medium text-brand">{{ __('Sign Up') }}</a>
    </p>
    <p class="mt-4 rounded-xl bg-slate-50 p-3 text-center text-xs text-slate-500">
        {{ __('Demo') }}: <b>admin@collabspace.test</b> / <b>password</b>
    </p>
</x-layouts.guest>
