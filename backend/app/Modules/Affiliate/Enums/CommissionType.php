<?php

namespace App\Modules\Affiliate\Enums;

enum CommissionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
