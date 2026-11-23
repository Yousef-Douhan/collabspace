@props(['name', 'title', 'width' => 'max-w-lg'])
{{-- Open with: $dispatch('open-modal', '{{ $name }}') --}}
<div x-data="{ open: {{ $errors->any() && old('_modal') === $name ? 'true' : 'false' }} }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:keydown.escape.window="open = false"
     x-cloak x-show="open" x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div @click.outside="open = false" x-show="open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="max-h-[90vh] w-full overflow-y-auto {{ $width }} rounded-3xl bg-white p-6 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold">{{ $title }}</h3>
            <button type="button" @click="open = false" class="rounded-full p-1 text-slate-400 hover:bg-slate-100" aria-label="Close">✕</button>
        </div>
        {{ $slot }}
    </div>
</div>
