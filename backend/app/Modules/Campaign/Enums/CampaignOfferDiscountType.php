<?php

namespace App\Modules\Campaign\Enums;

enum CampaignOfferDiscountType: string
{
    case None = 'none';
    case Percent = 'percent';
    case Fixed = 'fixed';
}
