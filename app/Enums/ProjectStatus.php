<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ProjectStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
