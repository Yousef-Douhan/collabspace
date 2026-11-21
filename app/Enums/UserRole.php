<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserRole: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }
}
