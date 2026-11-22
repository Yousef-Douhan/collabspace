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

    <x-modal name="new-meeting" :title="__('Schedule Meeting')" width="max-w-xl">
        <form method="POST" action="{{ route('meetings.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="_modal" value="new-meeting">
            <div><label class="label">{{ __('Title') }}</label><input name="title" value="{{ old('title') }}" required class="input"></div>
            <div><label class="label">{{ __('Agenda') }}</label><textarea name="agenda" rows="2" class="input">{{ old('agenda') }}</textarea></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">{{ __('Starts') }}</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required class="input"></div>
                <div><label class="label">{{ __('Ends') }}</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required class="input"></div>
                <div><label class="label">{{ __('Project') }}</label>
                    <select name="project_id" class="input"><option value="">{{ __('— None —') }}</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected(old('project_id') == $p->id)>{{ $p->name }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Reminder') }}</label>
                    <select name="reminder_minutes" class="input">@foreach ([0 => 'None', 5 => '5 min before', 15 => '15 min before', 30 => '30 min before', 60 => '1 hour before'] as $v => $l)<option value="{{ $v }}" @selected(old('reminder_minutes', 15) == $v)>{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="label">{{ __('Location') }}</label><input name="location" value="{{ old('location') }}" class="input"></div>
                <div><label class="label">{{ __('Meeting link') }}</label><input type="url" name="meeting_link" value="{{ old('meeting_link') }}" placeholder="{{ __('https://meet.google.com/…') }}" class="input"></div>
            </div>
            <div><label class="label">{{ __('Attendees') }}</label>
                <select name="attendees[]" multiple class="input h-28">@foreach ($users as $u)@continue($u->id === auth()->id())<option value="{{ $u->id }}" @selected(in_array($u->id, old('attendees', [])))>{{ $u->name }}</option>@endforeach</select>
            </div>
            <button class="btn-primary w-full">{{ __('Schedule') }}</button>
        </form>
    </x-modal>
</x-layouts.app>
