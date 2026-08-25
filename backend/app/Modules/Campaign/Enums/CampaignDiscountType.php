<?php

namespace App\Modules\Campaign\Enums;

enum CampaignDiscountType: string
{
    case None = 'none';
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';
}
