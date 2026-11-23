@php
    $ext = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
    $tone = match (true) {
        in_array($ext, ['pdf']) => 'bg-rose-50 text-rose-600',
        in_array($ext, ['doc', 'docx', 'txt', 'md']) => 'bg-brand-50 text-brand',
        in_array($ext, ['xls', 'xlsx', 'csv']) => 'bg-emerald-50 text-emerald-600',
        in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'fig']) => 'bg-violet-50 text-violet-600',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<div class="card lift flex items-center gap-3 p-4">
    <span class="{{ $tone }} flex size-11 shrink-0 items-center justify-center rounded-xl text-[11px] font-bold uppercase">{{ $ext ?: 'file' }}</span>
    <div class="min-w-0 flex-1">
        <a href="{{ route('files.download', $file) }}" class="block truncate text-sm font-medium hover:text-brand" title="{{ $file->original_name }}">{{ $file->original_name }}</a>
        <div class="truncate text-xs text-slate-500">{{ $file->formatted_size }} · {{ $file->project?->name }} · {{ $file->created_at->diffForHumans() }}</div>
    </div>
    <a href="{{ route('files.download', $file) }}" class="rounded-full p-2 text-slate-400 hover:bg-slate-100" title="{{ __('Download') }}"><x-icon name="download" /></a>
    @can('delete', $file)
        <form method="POST" action="{{ route('files.destroy', $file) }}" onsubmit="return confirm(@js(__('Delete this file?')))">
            @csrf @method('DELETE')
            <button class="rounded-full p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="{{ __('Delete') }}"><x-icon name="trash" /></button>
        </form>
    @endcan
</div>
