<?php

namespace App\Modules\Order\Enums;

enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Bot = 'bot';
    case Unknown = 'unknown';
}
