<x-modal name="upload" :title="__('Upload File')">
    <form method="POST" action="{{ route('files.store') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <input type="hidden" name="_modal" value="upload">
        <div>
            <label class="label">{{ __('Project') }}</label>
            <select name="project_id" class="input" required>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}" @selected(old('project_id', $selected ?? null) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        @isset($taskId)<input type="hidden" name="task_id" value="{{ $taskId }}">@endisset
        <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 hover:border-brand"
               x-data="{ name: '' }">
            <x-icon name="upload" size="8" class="text-brand" />
            <span x-text="name || @js(__('Click to choose a file (max 20 MB)'))"></span>
            <input type="file" name="file" required class="hidden" @change="name = $event.target.files[0]?.name">
        </label>
        <button class="btn-primary w-full">{{ __('Upload') }}</button>
    </form>
</x-modal>
