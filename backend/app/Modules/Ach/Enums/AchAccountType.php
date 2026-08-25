<?php

namespace App\Modules\Ach\Enums;

enum AchAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Checking',
            self::Savings => 'Savings',
        };
    }
}
