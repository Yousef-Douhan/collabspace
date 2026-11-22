<x-layouts.guest :title="__('Sign up')">
    <div class="text-center">
        <h1 class="text-2xl font-semibold">{{ __('Sign Up') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Create your workspace account') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label class="label">{{ __('Full name') }}</label>
            <input name="name" value="{{ old('name') }}" required class="input">
        </div>
        <div>
            <label class="label">{{ __('Email') }}</label>
            <input name="email" type="email" value="{{ old('email') }}" required class="input">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">{{ __('Job title') }}</label>
                <input name="job_title" value="{{ old('job_title') }}" class="input">
            </div>
            <div>
                <label class="label">{{ __('Team') }}</label>
                <select name="track" class="input">
                    @foreach (\App\Enums\Track::cases() as $track)
                        <option value="{{ $track->value }}" @selected(old('track') === $track->value)>{{ $track->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="label">{{ __('Password') }}</label>
            <input name="password" type="password" required class="input">
        </div>
        <div>
            <label class="label">{{ __('Confirm password') }}</label>
            <input name="password_confirmation" type="password" required class="input">
        </div>
        @if ($errors->any())
            <ul class="list-disc ps-5 text-xs text-rose-600">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
        <button class="btn-primary w-full">{{ __('Create account') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-medium text-brand">{{ __('Sign In') }}</a>
    </p>
</x-layouts.guest>
