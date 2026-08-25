<?php

namespace App\Modules\Inventory\Enums;

enum InventoryMovementType: string
{
    case Adjustment = 'adjustment';
    case Restock = 'restock';
    case Sale = 'sale';
    case Return = 'return';
    case ReservationCommit = 'reservation_commit';
}
