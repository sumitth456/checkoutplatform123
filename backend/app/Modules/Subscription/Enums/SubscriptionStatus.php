<?php

namespace App\Modules\Subscription\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function isBillable(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
