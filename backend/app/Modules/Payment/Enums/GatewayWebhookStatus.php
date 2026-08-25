<?php

namespace App\Modules\Payment\Enums;

enum GatewayWebhookStatus: string
{
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
