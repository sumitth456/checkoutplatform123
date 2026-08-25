<?php

namespace App\Modules\Subscription\Enums;

enum BillingStepType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
