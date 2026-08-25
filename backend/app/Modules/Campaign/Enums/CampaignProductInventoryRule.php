<?php

namespace App\Modules\Campaign\Enums;

enum CampaignProductInventoryRule: string
{
    case Inherit = 'inherit';
    case EnforceLimits = 'enforce_limits';
    case IgnoreInventory = 'ignore_inventory';
    case BlockOversell = 'block_oversell';
}
