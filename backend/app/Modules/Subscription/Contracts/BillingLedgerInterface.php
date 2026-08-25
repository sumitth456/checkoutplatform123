<?php

namespace App\Modules\Subscription\Contracts;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;

interface BillingLedgerInterface
{
    public function recordAttempt(
        Subscription $subscription,
        BillingLedgerOutcome $outcome,
        string $amount,
        string $currency,
        string $idempotencyKey,
        int $cycleStep,
        int $retryAttempt,
        ?PaymentResponse $response = null,
        ?string $failureCode = null,
        ?string $failureMessage = null,
    ): BillingLedgerEntry;
}
