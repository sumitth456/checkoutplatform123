<?php

namespace App\Modules\Email\Enums;

enum EmailQueueStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Retry = 'retry';
    case Cancelled = 'cancelled';
    case Dead = 'dead';
}
