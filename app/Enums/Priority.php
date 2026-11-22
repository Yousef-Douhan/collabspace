<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum Priority: string
{
    use EnumHelpers;

    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return __(match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        });
    }
}
