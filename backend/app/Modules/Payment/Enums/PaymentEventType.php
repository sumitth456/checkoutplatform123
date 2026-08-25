<?php

namespace App\Modules\Payment\Enums;

enum PaymentEventType: string
{
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Voided = 'voided';
    case Disputed = 'disputed';
}
