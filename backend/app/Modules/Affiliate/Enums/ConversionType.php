<?php

namespace App\Modules\Affiliate\Enums;

enum ConversionType: string
{
    case Sale = 'sale';
    case Lead = 'lead';
    case Rebill = 'rebill';
    case Refund = 'refund';
}
