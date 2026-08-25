<?php

namespace App\Modules\CallCenter\Enums;

enum CallType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
