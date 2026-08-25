<?php

namespace App\Modules\Campaign\Enums;

enum CampaignFunnelMappingStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
