<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttachmentType: string
{
    use EnumHelpers;

    case Image = 'image';
    case Audio = 'audio';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Audio => 'Audio',
            self::Document => 'Document',
        };
    }
}
