<?php

namespace App\Modules\Subscription\Events;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRebillFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $reason,
        public readonly ?BillingLedgerEntry $ledgerEntry = null,
    ) {}
}
