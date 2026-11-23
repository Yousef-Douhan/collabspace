<x-layouts.app :title="__('Settings')">
    <x-page-header :title="__('Settings')" :crumbs="['Profile' => route('members.show', $user)]" />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">
        <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data"
              class="card h-fit space-y-3 p-5 text-center"
              x-data="{ preview: null, busy: false }">
            @csrf
            <label class="group relative mx-auto block size-24 cursor-pointer" title="{{ __('Change photo') }}">
                <template x-if="preview"><img :src="preview" alt="" class="size-24 rounded-full object-cover"></template>
                <div x-show="!preview" class="flex justify-center"><x-avatar :user="$user" size="24" /></div>
                <span class="absolute inset-0 flex items-center justify-center rounded-full bg-slate-900/50 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100">{{ __('Change photo') }}</span>
                <input type="file" name="avatar" accept="image/*" class="sr-only"
                       @change="if ($event.target.files[0]) { preview = URL.createObjectURL($event.target.files[0]); busy = true; $el.form.submit(); }">
            </label>
            <div class="font-medium">{{ $user->name }}</div>
            <div class="text-xs text-slate-500">{{ $user->job_title }}</div>
            @error('avatar')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="text-[11px] text-slate-400" x-show="!busy">{{ __('Click the photo to change it (JPG, PNG, max 2 MB).') }}</p>
            <p class="text-[11px] text-brand" x-cloak x-show="busy">{{ __('Uploading…') }}</p>
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
                        <select name="locale" class="input"><option value="en" @selected($user->locale === 'en')>{{ __('English') }}</option><option value="ar" @selected($user->locale === 'ar')>العربية</option><option value="fr" @selected($user->locale === 'fr')>Français</option></select>
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
