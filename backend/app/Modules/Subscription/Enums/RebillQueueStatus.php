<?php

namespace App\Modules\Subscription\Enums;

enum RebillQueueStatus: string
{
    case Queued = 'queued';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
