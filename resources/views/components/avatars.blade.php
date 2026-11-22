@props(['users', 'max' => 3, 'size' => '7'])
<div class="flex -space-x-2">
    @foreach ($users->take($max) as $u)
        <x-avatar :user="$u" :size="$size" />
    @endforeach
    @if ($users->count() > $max)
        <span class="inline-flex size-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-medium text-slate-600 ring-2 ring-white">+{{ $users->count() - $max }}</span>
    @endif
</div>
