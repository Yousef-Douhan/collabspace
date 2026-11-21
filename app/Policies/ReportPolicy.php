<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return $user->isAdmin() || $report->created_by === $user->id;
    }

    public function delete(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }
}
