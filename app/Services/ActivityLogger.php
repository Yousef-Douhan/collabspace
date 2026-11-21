<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Writes the activity feed shown on the dashboard and member profiles. */
class ActivityLogger
{
    public function log(User $user, string $action, string $description, ?Model $subject = null, ?Project $project = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
        ]);
    }
}
