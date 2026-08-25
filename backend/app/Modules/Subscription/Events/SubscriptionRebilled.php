<?php

namespace App\Modules\Subscription\Events;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use App\Modules\Payment\DTOs\PaymentResponse;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRebilled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?PaymentResponse $response,
        public readonly ?BillingLedgerEntry $ledgerEntry = null,
    ) {}
}
