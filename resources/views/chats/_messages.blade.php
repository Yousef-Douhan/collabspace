{{-- Message list for a conversation; rendered on page load and by chats.poll every few seconds. --}}
@php $me = auth()->user(); @endphp
@forelse ($messages as $m)
    @php $mine = $m->sender_id === $me->id; @endphp
    <div @class(['flex gap-2', 'flex-row-reverse' => $mine, 'msg-in' => $loop->last]) x-data="{ editing: false }">
        @unless ($mine)<x-avatar :user="$m->sender" size="8" />@endunless
        <div @class(['group max-w-[70%]', 'text-end' => $mine])>
            @unless ($mine)<div class="mb-0.5 text-[11px] text-slate-500">{{ $m->sender->name }}</div>@endunless
            <div x-show="!editing" @class(['inline-block rounded-2xl px-4 py-2 text-start text-sm', 'bg-brand text-white rounded-ee-md' => $mine, 'bg-white shadow-sm rounded-es-md' => ! $mine])>
                @if ($m->body)<p dir="auto" class="whitespace-pre-line">{{ $m->body }}</p>@endif
                @if ($m->attachment_path)
                    <a href="{{ route('messages.attachment', $m) }}" @class(['mt-1 flex items-center gap-2 rounded-xl px-3 py-2 text-xs', 'bg-white/15' => $mine, 'bg-slate-100' => ! $mine])><x-icon name="paperclip" size="3.5" /> {{ $m->attachment_name }}</a>
                @endif
            </div>
            @if ($mine)
                <form x-cloak x-show="editing" method="POST" action="{{ route('messages.update', $m) }}" class="flex gap-2">
                    @csrf @method('PATCH')
                    <input name="body" value="{{ $m->body }}" class="input text-sm" required>
                    <button class="btn-primary px-3 text-xs">{{ __('Save') }}</button>
                    <button type="button" @click="editing = false" class="btn-ghost px-3 text-xs">✕</button>
                </form>
            @endif
            <div class="mt-0.5 flex items-center gap-2 text-[10px] text-slate-400 {{ $mine ? 'justify-end' : '' }}">
                <span>{{ $m->created_at->translatedFormat('H:i') }}</span>
                @if ($m->edited_at)<span>{{ __('· edited') }}</span>@endif
                @if ($mine)
                    <span title="{{ $m->readers->isNotEmpty() ? __('Seen by :names', ['names' => $m->readers->pluck('name')->implode(', ')]) : __('Sent') }}" @class(['text-brand' => $m->readers->isNotEmpty()])>{{ $m->readers->isNotEmpty() ? '✓✓' : '✓' }}</span>
                    @if ($m->body)<button @click="editing = true" class="hidden group-hover:inline">{{ __('Edit') }}</button>@endif
                    <form method="POST" action="{{ route('messages.destroy', $m) }}" class="hidden group-hover:inline" onsubmit="return confirm(@js(__('Delete this message?')))">
                        @csrf @method('DELETE')<button class="text-rose-500">{{ __('Delete') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@empty
    <x-empty>{{ __('Say hello 👋') }}</x-empty>
@endforelse
