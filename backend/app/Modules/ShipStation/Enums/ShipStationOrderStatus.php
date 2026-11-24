<?php

namespace App\Modules\ShipStation\Enums;

enum ShipStationOrderStatus: string
{
    case NotSent = 'not_sent';
    case Sent = 'sent';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
    case Error = 'error';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::NotSent => 'Not Sent',
            self::Sent => 'Sent',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Cancelled => 'Cancelled',
            self::Error => 'Error',
            self::Skipped => 'Skipped',
        };
    }
}
