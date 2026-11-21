<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MeetingResponse: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
        };
    }
}
