<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        return $user->isAdmin()
            || $meeting->created_by === $user->id
            || $meeting->attendees()->whereKey($user->id)->exists()
            || ($meeting->project && $meeting->project->hasMember($user));
    }

    /** Project meetings can only be scheduled by members of that project. */
    public function create(User $user, ?Project $project = null): bool
    {
        return $project === null || $user->isAdmin() || $project->hasMember($user);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $meeting->created_by === $user->id
            || $user->isAdmin()
            || ($meeting->project && $meeting->project->isManagedBy($user));
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    public function respond(User $user, Meeting $meeting): bool
    {
        return $meeting->attendees()->whereKey($user->id)->exists();
    }

    /** Anyone who attended (or organizes) can ask the AI to summarize. */
    public function summarize(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting) || $this->respond($user, $meeting);
    }
}
