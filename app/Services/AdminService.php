<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\AiMessage;
use App\Models\Meeting;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Site-wide administration: workspace statistics, user roles and account status.
 * Used by both the admin web pages and /api/v1/admin.
 */
class AdminService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * Whole-workspace numbers (admins see everything, so no visibility scope here).
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $today = now()->startOfDay();

        $tasksTotal = Task::count();
        $tasksCompleted = Task::where('status', TaskStatus::Completed)->count();

        return [
            'users' => [
                'total' => User::count(),
                'active' => User::whereNull('deactivated_at')->count(),
                'deactivated' => User::whereNotNull('deactivated_at')->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'by_role' => collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->value => User::where('role', $r)->count()])->all(),
            ],
            'projects' => [
                'total' => Project::count(),
                'active' => Project::where('status', ProjectStatus::Active)->count(),
                'completed' => Project::where('status', ProjectStatus::Completed)->count(),
                'overdue' => Project::where('status', ProjectStatus::Active)->whereDate('end_date', '<', $today)->count(),
            ],
            'tasks' => [
                'total' => $tasksTotal,
                'completed' => $tasksCompleted,
                'overdue' => Task::where('status', '!=', TaskStatus::Completed)->whereDate('due_date', '<', $today)->count(),
                'completion_rate' => $tasksTotal ? (int) round($tasksCompleted / $tasksTotal * 100) : 0,
            ],
            'files' => [
                'total' => ProjectFile::count(),
                'size_bytes' => (int) ProjectFile::sum('size_bytes'),
            ],
            'meetings_this_week' => Meeting::whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'messages_last_7_days' => Message::where('created_at', '>=', now()->subDays(7))->count(),
            'ai_questions_last_7_days' => AiMessage::where('role', 'user')->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Latest actions across the whole workspace.
     *
     * @return Collection<int, ActivityLog>
     */
    public function recentActivity(int $limit = 15): Collection
    {
        return ActivityLog::with(['user:id,name,avatar_path', 'project:id,name'])->latest('id')->limit($limit)->get();
    }

    /**
     * Users with workload counts; filters: q (name/email), role, status (active|deactivated).
     *
     * @param  array<string, string|null>  $filters
     * @return Builder<User>
     */
    public function users(array $filters = []): Builder
    {
        return User::query()
            ->withCount([
                'projects',
                'tasks as open_tasks_count' => fn (Builder $q) => $q->where('status', '!=', TaskStatus::Completed),
            ])
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $q) => $q->whereNull('deactivated_at'))
            ->when(($filters['status'] ?? null) === 'deactivated', fn (Builder $q) => $q->whereNotNull('deactivated_at'))
            ->orderBy('name');
    }

    public function changeRole(User $actor, User $user, UserRole $role): User
    {
        if ($user->is($actor) && $role !== UserRole::Admin) {
            throw ValidationException::withMessages(['role' => __('You cannot remove your own admin role.')]);
        }

        $this->guardLastAdmin($user, $role !== UserRole::Admin);

        $user->update(['role' => $role]);
        $this->activity->log($actor, 'user.role_changed', "changed {$user->name}'s role to {$role->label()}", $user);

        return $user;
    }

    public function setActive(User $actor, User $user, bool $active): User
    {
        if ($user->is($actor) && ! $active) {
            throw ValidationException::withMessages(['status' => __('You cannot deactivate your own account.')]);
        }

        $this->guardLastAdmin($user, ! $active);

        $user->forceFill(['deactivated_at' => $active ? null : now()])->save();

        if (! $active) {
            // Sign the user out everywhere: API tokens now, web sessions on their next request.
            $user->tokens()->delete();
        }

        $this->activity->log($actor, $active ? 'user.activated' : 'user.deactivated', ($active ? 'reactivated ' : 'deactivated ').$user->name, $user);

        return $user;
    }

    /** The workspace must always keep at least one active admin. */
    private function guardLastAdmin(User $user, bool $removingAdmin): void
    {
        if (! $removingAdmin || ! $user->isAdmin()) {
            return;
        }

        $otherAdmins = User::where('role', UserRole::Admin)->whereNull('deactivated_at')->whereKeyNot($user->id)->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages(['user' => __('The workspace needs at least one active admin.')]);
        }
    }
}
