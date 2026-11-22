<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum Track: string
{
    use EnumHelpers;

    case UiUx = 'ui_ux';
    case FrontEnd = 'front_end';
    case BackEnd = 'back_end';
    case GraphicDesign = 'graphic_design';

    public function label(): string
    {
        return __(match ($this) {
            self::UiUx => 'UI/UX',
            self::FrontEnd => 'Front End',
            self::BackEnd => 'Back End',
            self::GraphicDesign => 'Graphic Design',
        });
    }
}
