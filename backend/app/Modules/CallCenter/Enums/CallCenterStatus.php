<?php

namespace App\Modules\CallCenter\Enums;

enum CallCenterStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
}
