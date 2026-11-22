<x-layouts.app :title="__('Users')">
    <x-page-header :title="__('Admin panel')" />

    @include('admin._tabs')

    <form class="mb-4 flex flex-wrap items-center gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search by name or email') }}" class="input max-w-xs rounded-full">
        <select name="role" onchange="this.form.submit()" class="input w-auto rounded-full">
            <option value="">{{ __('All roles') }}</option>
            @foreach (\App\Enums\UserRole::cases() as $r)<option value="{{ $r->value }}" @selected(request('role') === $r->value)>{{ $r->label() }}</option>@endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="input w-auto rounded-full">
            <option value="">{{ __('Any status') }}</option>
            <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
            <option value="deactivated" @selected(request('status') === 'deactivated')>{{ __('Deactivated') }}</option>
        </select>
    </form>

    @if ($errors->hasAny(['role', 'status', 'user']))
        <div class="mb-4 rounded-xl bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first('role') ?: ($errors->first('status') ?: $errors->first('user')) }}</div>
    @endif

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
            <thead class="border-b border-slate-100 text-xs text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-start font-medium">{{ __('User') }}</th>
                    <th class="px-3 py-3 text-start font-medium">{{ __('Track') }}</th>
                    <th class="px-3 py-3 text-start font-medium">{{ __('Projects') }}</th>
                    <th class="px-3 py-3 text-start font-medium">{{ __('Open tasks') }}</th>
                    <th class="px-3 py-3 text-start font-medium">{{ __('Role') }}</th>
                    <th class="px-3 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($users as $member)
                    @php $isMe = $member->id === auth()->id(); @endphp
                    <tr @class(['opacity-60' => ! $member->isActive()])>
                        <td class="px-5 py-3">
                            <a href="{{ route('members.show', $member) }}" class="flex items-center gap-3">
                                <x-avatar :user="$member" size="9" />
                                <span>
                                    <span class="block font-medium">{{ $member->name }} @if ($isMe)<span class="text-xs text-slate-400">({{ __('you') }})</span>@endif</span>
                                    <span class="block text-xs text-slate-500" dir="ltr">{{ $member->email }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="px-3 text-slate-600">{{ $member->track?->label() }}</td>
                        <td class="px-3">{{ $member->projects_count }}</td>
                        <td class="px-3">{{ $member->open_tasks_count }}</td>
                        <td class="px-3">
                            <form method="POST" action="{{ route('admin.users.role', $member) }}">
                                @csrf @method('PATCH')
                                <select name="role" onchange="this.form.submit()" @disabled($isMe) class="rounded-full border-slate-200 bg-slate-50 px-3 py-1 text-xs disabled:opacity-60">
                                    @foreach (\App\Enums\UserRole::cases() as $r)<option value="{{ $r->value }}" @selected($member->role === $r)>{{ $r->label() }}</option>@endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-3">
                            @if ($member->isActive())
                                <span class="chip bg-emerald-50 text-emerald-600">{{ __('Active') }}</span>
                            @else
                                <span class="chip bg-rose-50 text-rose-600" title="{{ $member->deactivated_at->diffForHumans() }}">{{ __('Deactivated') }}</span>
                            @endif
                        </td>
                        <td class="px-5 text-end">
                            @unless ($isMe)
                                <form method="POST" action="{{ route('admin.users.status', $member) }}"
                                      @if ($member->isActive()) onsubmit="return confirm(@js(__('Deactivate :name? They will be signed out and cannot sign in until reactivated.', ['name' => $member->name])))" @endif>
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="active" value="{{ $member->isActive() ? 0 : 1 }}">
                                    <button class="{{ $member->isActive() ? 'text-rose-600' : 'text-emerald-600' }} whitespace-nowrap text-xs font-medium hover:underline">
                                        {{ $member->isActive() ? __('Deactivate') : __('Reactivate') }}
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty>{{ __('No users match.') }}</x-empty></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
