<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin panel (route middleware `admin`). */
class AdminController extends Controller
{
    public function __construct(private AdminService $admin) {}

    public function index(): View
    {
        return view('admin.index', [
            'stats' => $this->admin->stats(),
            'activity' => $this->admin->recentActivity(),
        ]);
    }

    public function users(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', 'in:active,deactivated'],
        ]);

        return view('admin.users', [
            'users' => $this->admin->users($filters)->paginate(20)->withQueryString(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(UserRole::class)]]);

        $this->admin->changeRole($request->user(), $user, UserRole::from($data['role']));

        return back()->with('status', __(':name is now :role.', ['name' => $user->name, 'role' => $user->role->label()]));
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        $this->admin->setActive($request->user(), $user, (bool) $data['active']);

        return back()->with('status', $data['active']
            ? __(':name was reactivated.', ['name' => $user->name])
            : __(':name was deactivated.', ['name' => $user->name]));
    }
}
