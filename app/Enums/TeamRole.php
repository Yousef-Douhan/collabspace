<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TeamRole: string
{
    use EnumHelpers;

    case Lead = 'lead';
    case Member = 'member';

    public function label(): string
    {
        return __(match ($this) {
            self::Lead => 'Team lead',
            self::Member => 'Member',
        });
    }
}
