<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AiRole: string
{
    use EnumHelpers;

    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Assistant => 'Assistant',
        };
    }
}
