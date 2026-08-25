<?php

namespace App\Modules\Ach\Enums;

enum CheckoutPaymentMethod: string
{
    case Card = 'card';
    case Ach = 'ach';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Credit/Debit Card',
            self::Ach => 'ACH Bank Transfer',
        };
    }
}
