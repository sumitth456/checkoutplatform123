<?php

namespace App\Modules\Email\Enums;

enum EmailLogStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Bounced = 'bounced';
    case Spam = 'spam';
    case Unsubscribed = 'unsubscribed';
    case Failed = 'failed';
}
