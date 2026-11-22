<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum Availability: string
{
    use EnumHelpers;

    case Available = 'available';
    case Busy = 'busy';
    case Away = 'away';

    public function label(): string
    {
        return __(match ($this) {
            self::Available => 'Available',
            self::Busy => 'Busy',
            self::Away => 'Away',
        });
    }
}
