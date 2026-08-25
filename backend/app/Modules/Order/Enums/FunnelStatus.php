<?php



namespace App\Modules\Order\Enums;



enum FunnelStatus: string

{

    case Draft = 'draft';

    case Active = 'active';

    case Archived = 'archived';

}

