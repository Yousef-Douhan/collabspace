<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReportType: string
{
    use EnumHelpers;

    case ProjectProgress = 'project_progress';
    case TeamPerformance = 'team_performance';
    case Tasks = 'tasks';
    case Meetings = 'meetings';

    public function label(): string
    {
        return match ($this) {
            self::ProjectProgress => 'Project Progress',
            self::TeamPerformance => 'Team Performance',
            self::Tasks => 'Tasks',
            self::Meetings => 'Meetings',
        };
    }
}
