<?php

namespace App\Modules\Inventory\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Committed = 'committed';
    case Expired = 'expired';
}
