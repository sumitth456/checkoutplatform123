<?php

namespace App\Modules\StripeWebhook\Enums;

enum StripeWebhookProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Duplicate = 'duplicate';
}
