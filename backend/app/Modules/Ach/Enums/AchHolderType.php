<?php

namespace App\Modules\Ach\Enums;

enum AchHolderType: string
{
    case Personal = 'personal';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Business => 'Business',
        };
    }
}
