<?php

namespace App\Modules\Subscription\Enums;

enum BillingLedgerOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
}
