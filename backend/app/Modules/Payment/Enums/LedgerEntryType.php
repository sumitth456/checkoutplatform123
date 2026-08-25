<?php

namespace App\Modules\Payment\Enums;

enum LedgerEntryType: string
{
    case Authorize = 'authorize';
    case Capture = 'capture';
    case Refund = 'refund';
    case Void = 'void';
    case StatusCheck = 'status_check';
    case Webhook = 'webhook';
}
