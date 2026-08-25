<?php

namespace App\Modules\Subscription\Enums;

enum BillingCycleType: string
{
    case Trial = 'trial';
    case Day7Rebill = 'day_7_rebill';
    case Day30Rebill = 'day_30_rebill';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Day7Rebill => 'Day 7 Rebill',
            self::Day30Rebill => 'Day 30 Rebill',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Custom => 'Custom',
        };
    }
}
