@php
    $hours = range(8, 18);
    $palette = ['bg-brand-50 border-brand text-brand-700', 'bg-violet-50 border-violet-500 text-violet-700', 'bg-emerald-50 border-emerald-500 text-emerald-700', 'bg-amber-50 border-amber-500 text-amber-700', 'bg-pink-50 border-pink-500 text-pink-700'];
@endphp
<x-layouts.app :title="__('Meetings')">
    <x-page-header :title="__('Meetings')">
        <div class="flex items-center gap-1 rounded-full bg-white p-1 shadow-sm">
            <a href="{{ route('meetings.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}" class="rounded-full px-3 py-1 hover:bg-slate-100">‹</a>
            <a href="{{ route('meetings.index') }}" class="rounded-full px-3 py-1 text-sm font-medium hover:bg-slate-100">{{ __('This Week') }}</a>
            <a href="{{ route('meetings.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}" class="rounded-full px-3 py-1 hover:bg-slate-100">›</a>
        </div>
        <button class="btn-primary" x-data @click="$dispatch('open-modal', 'new-meeting')">{{ __('Schedule Meeting +') }}</button>
    </x-page-header>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_280px]">
        <div class="card overflow-x-auto">
            <div class="min-w-[760px]">
                <div class="grid grid-cols-[64px_repeat(7,1fr)] border-b border-slate-100 text-center text-xs">
                    <div class="py-3 text-slate-400">{{ $start->translatedFormat('M') }}</div>
                    @foreach ($days as $day)
                        <div @class(['py-3', 'text-brand font-semibold' => $day->isToday()])>
                            <div class="text-slate-500">{{ $day->translatedFormat('D') }}</div>
                            <div @class(['mx-auto mt-1 flex size-7 items-center justify-center rounded-full text-sm', 'bg-brand text-white' => $day->isToday()])>{{ $day->translatedFormat('j') }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="relative grid grid-cols-[64px_repeat(7,1fr)]">
                    {{-- Hour rows --}}
                    <div>
                        @foreach ($hours as $h)
                            <div class="h-16 border-b border-slate-50 pe-2 pt-1 text-end text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::createFromTime($h)->translatedFormat('g A') }}</div>
                        @endforeach
                    </div>
                    @foreach ($days as $i => $day)
                        <div class="relative border-s border-slate-50">
                            @foreach ($hours as $h)<div class="h-16 border-b border-slate-50"></div>@endforeach

                            @foreach ($meetings->filter(fn ($m) => $m->starts_at->isSameDay($day)) as $m)
                                @php
                                    $top = max(0, ($m->starts_at->hour + $m->starts_at->minute / 60 - $hours[0]) * 64);
                                    $height = max(28, $m->starts_at->diffInMinutes($m->ends_at) / 60 * 64 - 4);
                                @endphp
                                <a href="{{ route('meetings.show', $m) }}" style="top: {{ $top }}px; height: {{ $height }}px"
                                   class="{{ $palette[$m->id % count($palette)] }} absolute inset-x-1 overflow-hidden rounded-lg border-s-4 px-2 py-1 text-[11px] hover:shadow">
                                    <div class="truncate font-semibold">{{ $m->title }}</div>
                                    <div class="opacity-80" dir="ltr">{{ $m->starts_at->translatedFormat('H:i') }}–{{ $m->ends_at->translatedFormat('H:i') }}</div>
                                    @if ($height > 50)<div class="mt-1"><x-avatars :users="$m->attendees" size="6" /></div>@endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="card h-fit p-5">
            <h2 class="mb-3 font-semibold">{{ __('Upcoming') }}</h2>
            @forelse ($upcoming as $m)
                <a href="{{ route('meetings.show', $m) }}" class="mb-2 block rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                    <div class="text-sm font-medium">{{ $m->title }}</div>
                    <div class="text-xs text-slate-500">{{ $m->starts_at->translatedFormat('D, M d · H:i') }}</div>
                </a>
            @empty
                <x-empty class="py-4">{{ __('Nothing scheduled.') }}</x-empty>
            @endforelse
        </aside>
    </div>

    {{-- New Meeting (Figma "Add Meeting overlay": subject · date · time · duration · guests · note · reminder · notify via · attachments) --}}
    <x-modal name="new-meeting" :title="__('New Meeting')" width="max-w-2xl">
        <form method="POST" action="{{ route('meetings.store') }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{
                  date: @js(old('starts_at') ? \Illuminate\Support\Str::before(old('starts_at'), 'T') : now()->addDay()->toDateString()),
                  time: @js(old('starts_at') ? \Illuminate\Support\Str::after(old('starts_at'), 'T') : '10:00'),
                  duration: 30,
                  files: [],
                  pad: (n) => String(n).padStart(2, '0'),
                  get startsAt() { return this.date && this.time ? `${this.date}T${this.time}` : ''; },
                  get endsAt() {
                      if (!this.startsAt) return '';
                      const d = new Date(this.startsAt); d.setMinutes(d.getMinutes() + Number(this.duration));
                      return `${d.getFullYear()}-${this.pad(d.getMonth() + 1)}-${this.pad(d.getDate())}T${this.pad(d.getHours())}:${this.pad(d.getMinutes())}`;
                  },
              }">
            @csrf
            <input type="hidden" name="_modal" value="new-meeting">
            <input type="hidden" name="starts_at" :value="startsAt">
            <input type="hidden" name="ends_at" :value="endsAt">

            <div><label class="label">{{ __('Meeting Subject') }}</label><input name="title" value="{{ old('title') }}" required class="input"></div>

            <div class="grid grid-cols-3 gap-3">
                <div><label class="label">{{ __('Select Date') }}</label><input type="date" x-model="date" required class="input"></div>
                <div><label class="label">{{ __('Select Time') }}</label><input type="time" x-model="time" required class="input"></div>
                <div><label class="label">{{ __('Select Duration') }}</label>
                    <select x-model="duration" class="input">
                        @foreach ([15, 30, 45, 60, 90, 120] as $min)<option value="{{ $min }}">{{ trans_choice(':count minute|:count minutes', $min) }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div><label class="label">{{ __('Add Guests') }}</label>
                <select name="attendees[]" multiple class="input h-24">@foreach ($users as $u)@continue($u->id === auth()->id())<option value="{{ $u->id }}" @selected(in_array($u->id, old('attendees', [])))>{{ $u->name }}</option>@endforeach</select>
            </div>

            <div><label class="label">{{ __('Add Note') }}</label><textarea name="agenda" rows="2" dir="auto" placeholder="{{ __('The goal of this meeting is…') }}" class="input">{{ old('agenda') }}</textarea></div>

            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">{{ __('Project') }}</label>
                    <select name="project_id" class="input"><option value="">{{ __('— None —') }}</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected(old('project_id') == $p->id)>{{ $p->name }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Location') }}</label>
                    <input name="location" list="meeting-locations" value="{{ old('location', __('Meeting room')) }}" class="input">
                    <datalist id="meeting-locations"><option value="{{ __('Meeting room') }}"><option value="{{ __('Online') }}"></datalist>
                </div>
                <div class="col-span-2"><label class="label">{{ __('Meeting link') }}</label><input type="url" name="meeting_link" value="{{ old('meeting_link') }}" placeholder="https://meet.google.com/…" class="input"></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">{{ __('Set Reminder') }}</label>
                    <select name="reminder_minutes" class="input">@foreach ([0 => 'None', 5 => '5 min before', 15 => '15 min before', 30 => '30 min before', 60 => '1 hour before event', 1440 => '1 day before'] as $v => $l)<option value="{{ $v }}" @selected(old('reminder_minutes', 15) == $v)>{{ __($l) }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Notification') }}</label>
                    <div class="flex flex-wrap gap-3 pt-2 text-sm">
                        <label class="flex items-center gap-1.5"><input type="checkbox" name="notify_channels[]" value="app" checked disabled> {{ __('In-app') }}</label>
                        <label class="flex items-center gap-1.5"><input type="checkbox" name="notify_channels[]" value="email" @checked(in_array('email', old('notify_channels', [])))> {{ __('Email') }}</label>
                        <label class="flex items-center gap-1.5"><input type="checkbox" name="notify_channels[]" value="slack" @checked(in_array('slack', old('notify_channels', [])))> Slack</label>
                    </div>
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-slate-200 p-4 text-sm text-slate-500 hover:border-brand">
                <x-icon name="paperclip" size="5" class="text-brand" />
                <span x-text="files.length ? files.join(', ') : @js(__('Upload Attachment (optional, max 20 MB each)'))" class="truncate"></span>
                <input type="file" name="attachments[]" multiple class="hidden" @change="files = [...$event.target.files].map(f => f.name)">
            </label>

            <button class="btn-primary w-full">{{ __('Add Meeting') }}</button>
        </form>
    </x-modal>
</x-layouts.app>
