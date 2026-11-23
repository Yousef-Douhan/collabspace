@php $me = auth()->user(); @endphp
<x-layouts.app :title="__('Chats')">
    <div class="card grid h-[calc(100vh-9rem)] min-h-[520px] grid-cols-1 overflow-hidden md:grid-cols-[320px_1fr]">
        {{-- Conversation list --}}
        <aside class="flex min-h-0 flex-col border-e border-slate-100">
            <div class="flex items-center justify-between px-5 pt-5">
                <h1 class="text-lg font-semibold">{{ __('Chats') }}</h1>
                <button class="rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand" x-data @click="$dispatch('open-modal', 'new-chat')">{{ __('New +') }}</button>
            </div>
            <div class="mx-5 mt-4 grid grid-cols-2 rounded-full bg-slate-100 p-1 text-sm">
                <a href="{{ route('chats.index', ['tab' => 'inbox']) }}" @class(['rounded-full py-1.5 text-center', 'bg-white font-medium shadow-sm' => $tab === 'inbox', 'text-slate-500' => $tab !== 'inbox'])>{{ __('Inbox') }}</a>
                <a href="{{ route('chats.index', ['tab' => 'projects']) }}" @class(['rounded-full py-1.5 text-center', 'bg-white font-medium shadow-sm' => $tab === 'projects', 'text-slate-500' => $tab !== 'projects'])>{{ __('Project Chats') }}</a>
            </div>
            <div class="mt-3 min-h-0 flex-1 overflow-y-auto px-2 pb-3">
                @forelse ($conversations as $c)
                    @php $other = $c->participants->firstWhere('id', '!=', $me->id); @endphp
                    <a href="{{ route('chats.show', [$c, 'tab' => $tab]) }}" @class(['flex items-center gap-3 rounded-2xl px-3 py-2.5', 'bg-brand-50' => $conversation?->id === $c->id, 'hover:bg-slate-50' => $conversation?->id !== $c->id])>
                        @if ($c->type === \App\Enums\ConversationType::Direct && $other)
                            <x-avatar :user="$other" size="9" />
                        @else
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700">#</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium">@if ($c->pivot->is_pinned)<x-icon name="pin" size="3" class="me-1 inline text-brand" />@endif{{ $c->displayNameFor($me) }}</span>
                                <span class="shrink-0 text-[11px] text-slate-400">{{ $c->latestMessage?->created_at?->translatedFormat('H:i') }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-slate-500">{{ $c->latestMessage ? \Illuminate\Support\Str::limit($c->latestMessage->body ?? '📎 '.$c->latestMessage->attachment_name, 40) : 'No messages yet' }}</span>
                                @if ($c->unread_count)
                                    <span class="rounded-full bg-brand px-1.5 text-[10px] font-semibold text-white">{{ $c->unread_count }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <x-empty>{{ __('No conversations.') }}</x-empty>
                @endforelse
            </div>
        </aside>

        {{-- Thread --}}
        <section class="flex min-h-0 flex-col">
            @if ($conversation)
                <header class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                    <div class="flex-1">
                        <div class="font-semibold">{{ $conversation->displayNameFor($me) }}</div>
                        <div class="text-xs text-slate-500">{{ $conversation->participants->pluck('name')->implode(', ') }}</div>
                    </div>
                    @php $pivot = $conversation->participants->firstWhere('id', $me->id)?->pivot; @endphp
                    <form method="POST" action="{{ route('chats.state', $conversation) }}">@csrf @method('PATCH')
                        <input type="hidden" name="is_pinned" value="{{ $pivot?->is_pinned ? 0 : 1 }}">
                        <button class="btn-ghost text-xs">{{ $pivot?->is_pinned ? __('Unpin') : __('Pin') }}</button>
                    </form>
                    <form method="POST" action="{{ route('chats.state', $conversation) }}">@csrf @method('PATCH')
                        <input type="hidden" name="is_archived" value="1">
                        <button class="btn-ghost text-xs">{{ __('Archive') }}</button>
                    </form>
                </header>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/60 px-6 py-5"
                     x-data="chatThread(@js(route('chats.poll', $conversation)))">
                    @include('chats._messages')
                </div>

                <form method="POST" action="{{ route('chats.send', $conversation) }}" enctype="multipart/form-data" class="flex items-center gap-2 border-t border-slate-100 p-4" x-data="{ file: '' }">
                    @csrf
                    <label class="cursor-pointer rounded-full p-2 text-slate-400 hover:bg-slate-100" title="{{ __('Attach') }}"><x-icon name="paperclip" size="5" />
                        <input type="file" name="attachment" class="hidden" @change="file = $event.target.files[0]?.name">
                    </label>
                    <span x-cloak x-show="file" x-text="file" class="max-w-32 truncate text-xs text-slate-500"></span>
                    <input name="body" dir="auto" autocomplete="off" placeholder="{{ __('Type a message…') }}" class="input rounded-full" autofocus>
                    <button class="btn-primary inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap">{{ __('Send') }} <x-icon name="send" size="3.5" class="rtl:-scale-x-100" /></button>
                </form>
            @else
                <div class="flex flex-1 flex-col items-center justify-center text-center text-slate-400">
                    <span class="mb-3 flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand"><x-icon name="message" size="7" /></span>
                    <p class="text-sm">{{ __('Select a conversation to start chatting.') }}</p>
                </div>
            @endif
        </section>
    </div>

    <x-modal name="new-chat" :title="__('New Conversation')">
        <form method="POST" action="{{ route('chats.store') }}" class="space-y-3" x-data="{ type: '{{ old('type', 'direct') }}' }">
            @csrf
            <input type="hidden" name="_modal" value="new-chat">
            <div class="grid grid-cols-2 gap-2 text-sm">
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3"><input type="radio" name="type" value="direct" x-model="type"> {{ __('Direct message') }}</label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3"><input type="radio" name="type" value="channel" x-model="type"> {{ __('Group channel') }}</label>
            </div>
            <div x-show="type === 'direct'">
                <label class="label">{{ __('Person') }}</label>
                <select name="user_id" class="input" :disabled="type !== 'direct'">@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            </div>
            <div x-cloak x-show="type === 'channel'" class="space-y-3">
                <div><label class="label">{{ __('Channel name') }}</label><input name="name" class="input" :disabled="type !== 'channel'"></div>
                <div><label class="label">{{ __('Members') }}</label>
                    <select name="members[]" multiple class="input h-28" :disabled="type !== 'channel'">@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                </div>
            </div>
            <button class="btn-primary w-full">{{ __('Start') }}</button>
        </form>
    </x-modal>
</x-layouts.app>
