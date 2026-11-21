<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ProjectRole: string
{
    use EnumHelpers;

    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }
}
