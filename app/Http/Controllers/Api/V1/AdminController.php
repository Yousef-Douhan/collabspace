<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** /api/v1/admin/* — admins only (route middleware `admin`). */
class AdminController extends Controller
{
    public function __construct(private AdminService $admin) {}

    /** GET /admin/stats */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->admin->stats(),
            'recent_activity' => $this->admin->recentActivity()->map(fn ($a) => [
                'id' => $a->id,
                'user' => $a->user?->name,
                'action' => $a->action,
                'description' => $a->description,
                'project' => $a->project?->name,
                'created_at' => $a->created_at->toIso8601String(),
            ]),
        ]]);
    }

    /** GET /admin/users?q=&role=&status=active|deactivated */
    public function users(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', 'in:active,deactivated'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $users = $this->admin->users($filters)->paginate($filters['per_page'] ?? 20)->withQueryString();

        return UserResource::collection($users);
    }

    /** PATCH /admin/users/{user}/role  {role} */
    public function updateRole(Request $request, User $user): UserResource
    {
        $data = $request->validate(['role' => ['required', Rule::enum(UserRole::class)]]);

        return new UserResource($this->admin->changeRole($request->user(), $user, UserRole::from($data['role'])));
    }

    /** PATCH /admin/users/{user}/status  {active: true|false} */
    public function updateStatus(Request $request, User $user): UserResource
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        return new UserResource($this->admin->setActive($request->user(), $user, (bool) $data['active']));
    }
}
