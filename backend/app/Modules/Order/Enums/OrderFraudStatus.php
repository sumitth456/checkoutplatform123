<?php

namespace App\Modules\Order\Enums;

enum OrderFraudStatus: string
{
    case Clear = 'clear';
    case Approved = 'approved';
    case Review = 'review';
    case Blocked = 'blocked';
}
