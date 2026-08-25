<?php

namespace App\Modules\Order\Enums;

enum OrderFulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Partial = 'partial';
    case Fulfilled = 'fulfilled';
}
