<x-layouts.app :title="__('Files')">
    <x-page-header :title="__('Files')">
        <button class="btn-primary" x-data @click="$dispatch('open-modal', 'upload')">{{ __('Upload File +') }}</button>
    </x-page-header>

    <h2 class="mb-3 font-semibold">{{ __('Recent Files') }}</h2>
    <div class="stagger mb-8 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        @forelse ($recent as $file)
            @include('files._card')
        @empty
            <x-empty class="col-span-full">{{ __('No files yet.') }}</x-empty>
        @endforelse
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">{{ __('All Files') }}</h2>
        <form class="flex w-full flex-wrap gap-2 sm:w-auto">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search files') }}" class="input min-w-0 flex-1 rounded-full sm:w-56 sm:flex-none">
            <select name="project_id" onchange="this.form.submit()" class="input w-auto rounded-full">
                <option value="">{{ __('All projects') }}</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </form>
    </div>
    <div class="stagger grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($files as $file)
            @include('files._card')
        @empty
            <x-empty class="col-span-full">{{ __('No files match.') }}</x-empty>
        @endforelse
    </div>
    <div class="mt-6">{{ $files->links() }}</div>

    @include('files._upload', ['selected' => request('project_id')])
</x-layouts.app>
