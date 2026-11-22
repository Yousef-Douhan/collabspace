<x-layouts.app :title="__('Settings')">
    <x-page-header :title="__('Settings')" :crumbs="['Profile' => route('members.show', $user)]" />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">
        <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="card h-fit space-y-3 p-5 text-center">
            @csrf
            <div class="flex justify-center"><x-avatar :user="$user" size="16" /></div>
            <input type="file" name="avatar" accept="image/*" required class="w-full text-xs">
            @error('avatar')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            <button class="btn-ghost w-full">{{ __('Update photo') }}</button>
        </form>

        <div class="space-y-5">
            <form method="POST" action="{{ route('settings.update') }}" class="card space-y-3 p-5">
                @csrf @method('PUT')
                <h2 class="font-semibold">{{ __('Profile') }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div><label class="label">{{ __('Name') }}</label><input name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
                    <div><label class="label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></div>
                    <div><label class="label">{{ __('Phone') }}</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div>
                    <div><label class="label">{{ __('Job title') }}</label><input name="job_title" value="{{ old('job_title', $user->job_title) }}" class="input"></div>
                    <div><label class="label">{{ __('Track') }}</label>
                        <select name="track" class="input">@foreach (\App\Enums\Track::cases() as $t)<option value="{{ $t->value }}" @selected($user->track === $t)>{{ $t->label() }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Availability') }}</label>
                        <select name="availability" class="input">@foreach (\App\Enums\Availability::cases() as $a)<option value="{{ $a->value }}" @selected($user->availability === $a)>{{ $a->label() }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Years of experience') }}</label><input type="number" min="0" max="60" name="years_of_experience" value="{{ old('years_of_experience', $user->years_of_experience ?? 0) }}" class="input"></div>
                    <div><label class="label">{{ __('Language') }}</label>
                        <select name="locale" class="input"><option value="en" @selected($user->locale === 'en')>{{ __('English') }}</option><option value="ar" @selected($user->locale === 'ar')>العربية</option></select>
                    </div>
                </div>
                <div><label class="label">{{ __('About') }}</label><textarea name="about" rows="3" dir="auto" class="input">{{ old('about', $user->about) }}</textarea></div>
                <button class="btn-primary">{{ __('Save profile') }}</button>
            </form>

            <form method="POST" action="{{ route('settings.password') }}" class="card space-y-3 p-5">
                @csrf @method('PUT')
                <h2 class="font-semibold">{{ __('Change password') }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div><label class="label">{{ __('Current password') }}</label><input type="password" name="current_password" required class="input"></div>
                    <div><label class="label">{{ __('New password') }}</label><input type="password" name="password" required class="input"></div>
                    <div><label class="label">{{ __('Confirm') }}</label><input type="password" name="password_confirmation" required class="input"></div>
                </div>
                <button class="btn-primary">{{ __('Change password') }}</button>
            </form>
        </div>
    </div>
</x-layouts.app>
