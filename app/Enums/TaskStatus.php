<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TaskStatus: string
{
    use EnumHelpers;

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Completed = 'completed';

    public function label(): string
    {
        return __(match ($this) {
            self::Todo => 'To-do',
            self::InProgress => 'In Progress',
            self::InReview => 'In Review',
            self::Completed => 'Completed',
        });
    }
}
