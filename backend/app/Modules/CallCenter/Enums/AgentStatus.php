<?php

namespace App\Modules\CallCenter\Enums;

enum AgentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnCall = 'on_call';
    case Break = 'break';
    case Offline = 'offline';
}
