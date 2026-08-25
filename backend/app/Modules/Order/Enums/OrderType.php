<?php

namespace App\Modules\Order\Enums;

enum OrderType: string
{
    case Initial = 'initial';
    case Rebill = 'rebill';
}
