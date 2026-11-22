<div class="mb-6 flex gap-6 border-b border-slate-200">
    <a href="{{ route('admin.index') }}" @class(['tab', 'tab-active' => request()->routeIs('admin.index')])>{{ __('Overview') }}</a>
    <a href="{{ route('admin.users') }}" @class(['tab', 'tab-active' => request()->routeIs('admin.users')])>{{ __('Users') }}</a>
</div>
