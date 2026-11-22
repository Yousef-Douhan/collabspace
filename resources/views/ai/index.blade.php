<x-layouts.app :title="__('Ask AI')">
    <div class="card mx-auto flex h-[calc(100vh-9rem)] min-h-[520px] max-w-3xl flex-col overflow-hidden">
        <header class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h1 class="flex items-center gap-2 font-semibold"><x-icon name="bot" size="5" class="text-brand" />{{ __('Ask AI') }}</h1>
            @if ($messages->isNotEmpty())
                <form method="POST" action="{{ route('ai.clear') }}" onsubmit="return confirm(@js(__('Clear the conversation?')))">@csrf @method('DELETE')
                    <button class="text-xs text-slate-500 hover:text-rose-600">{{ __('Clear chat') }}</button>
                </form>
            @endif
        </header>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5" x-init="$el.scrollTop = $el.scrollHeight">
            @forelse ($messages as $m)
                @if ($m->role->value === 'user')
                    <div class="flex justify-end"><p dir="auto" class="max-w-[75%] whitespace-pre-line rounded-2xl rounded-ee-md bg-brand px-4 py-2 text-sm text-white">{{ $m->content }}</p></div>
                @else
                    <div class="flex gap-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand"><x-icon name="sparkles" /></span>
                        <div dir="auto" class="prose-chat max-w-[80%] rounded-2xl rounded-es-md bg-slate-50 px-4 py-2.5 text-sm">{!! \Illuminate\Support\Str::markdown($m->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    </div>
                @endif
            @empty
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <h2 class="text-2xl font-semibold">{{ __('Hi There 👋') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Ask me anything about your projects, tasks, meetings and team.') }}</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        @foreach ([__('What tasks are overdue?'), __('Summarize my week'), __('Which tasks are high priority?'), __('Who has the most open tasks?')] as $suggestion)
                            <form method="POST" action="{{ route('ai.ask') }}">@csrf
                                <input type="hidden" name="message" value="{{ $suggestion }}">
                                <button dir="auto" class="rounded-full border border-slate-200 px-3 py-1.5 text-xs hover:border-brand hover:text-brand">{{ $suggestion }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('ai.ask') }}" class="border-t border-slate-100 p-4" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            @error('message')<p class="mb-2 text-xs text-rose-600">{{ $message }}</p>@enderror
            <div class="flex gap-2">
                <input name="message" value="{{ old('message') }}" required maxlength="1000" dir="auto" autocomplete="off" autofocus placeholder="{{ __('Ask me anything…') }}" class="input rounded-full">
                <button class="btn-primary shrink-0 whitespace-nowrap" :disabled="busy"><span x-show="!busy" class="inline-flex items-center gap-1.5">{{ __('Send') }} <x-icon name="send" size="3.5" class="rtl:-scale-x-100" /></span><span x-cloak x-show="busy">{{ __('Thinking…') }}</span></button>
            </div>
        </form>
    </div>
</x-layouts.app>
