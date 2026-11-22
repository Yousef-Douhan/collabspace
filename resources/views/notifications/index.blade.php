<x-layouts.app :title="__('Notifications')">
    <x-page-header :title="__('Notifications')">
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-ghost">{{ __('Mark all read') }}</button></form>
    </x-page-header>
    <div class="card divide-y divide-slate-50">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n->id) }}" @class(['flex items-start gap-3 px-5 py-4 hover:bg-slate-50', 'bg-brand-50/40' => ! $n->read_at])>
                <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-brand' }}"></span>
                <div class="flex-1">
                    <div class="text-sm font-medium">{{ $n->data['title'] ?? 'Notification' }}</div>
                    <div class="text-sm text-slate-500">{{ $n->data['message'] ?? '' }}</div>
                </div>
                <span class="shrink-0 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <x-empty>{{ __('You\'re all caught up.') }}</x-empty>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
