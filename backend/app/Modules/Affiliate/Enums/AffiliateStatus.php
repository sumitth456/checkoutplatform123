<?php

namespace App\Modules\Affiliate\Enums;

enum AffiliateStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
    case Rejected = 'rejected';
}
