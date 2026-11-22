<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ConversationType: string
{
    use EnumHelpers;

    case Direct = 'direct';
    case Channel = 'channel';

    public function label(): string
    {
        return __(match ($this) {
            self::Direct => 'Direct',
            self::Channel => 'Channel',
        });
    }
}
