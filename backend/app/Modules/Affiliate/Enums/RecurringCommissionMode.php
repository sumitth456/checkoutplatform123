<?php

namespace App\Modules\Affiliate\Enums;

enum RecurringCommissionMode: string
{
    case FirstOrderOnly = 'first_order_only';
    case EveryRebill = 'every_rebill';
    case FixedRebills = 'fixed_rebills';
    case Lifetime = 'lifetime';
}
