<?php

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Member profile pages + the signed-in user's settings. */
class MemberController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $tasks = $user->tasks()->with('project:id,name')->get();

        return view('members.show', [
            'member' => $user,
            'tab' => in_array($request->input('tab'), ['overview', 'tasks', 'files'], true) ? $request->input('tab') : 'overview',
            'tasks' => $tasks,
            'completed' => $tasks->where('status', TaskStatus::Completed)->count(),
            'hours' => (int) $tasks->where('status', TaskStatus::Completed)->sum('estimated_hours'),
            'projects' => $user->projects()->withCount('tasks')->get(),
            'files' => ProjectFile::visibleTo($request->user())->where('uploaded_by', $user->id)->with('project:id,name', 'uploader')->latest()->limit(12)->get(),
            'activity' => $user->activityLogs()->latest()->limit(10)->get(),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('members.settings', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', __('Profile saved.'));
    }

    public function avatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        return back()->with('status', __('Photo updated.'));
    }

    public function password(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        return back()->with('status', __('Password changed.'));
    }
}
