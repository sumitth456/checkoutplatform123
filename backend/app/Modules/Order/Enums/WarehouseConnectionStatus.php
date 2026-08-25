<?php



namespace App\Modules\Order\Enums;



enum WarehouseConnectionStatus: string

{

    case Unknown = 'unknown';

    case Healthy = 'healthy';

    case Unhealthy = 'unhealthy';

}

