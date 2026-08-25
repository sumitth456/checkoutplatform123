<?php

namespace App\Modules\Subscription\Repositories;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Subscription\Contracts\BillingLedgerInterface;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;

class BillingLedgerRepository implements BillingLedgerInterface
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
    ): BillingLedgerEntry {
        return BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => $cycleStep,
            'retry_attempt' => $retryAttempt,
            'outcome' => $outcome,
            'amount' => $amount,
            'currency' => $currency,
            'idempotency_key' => $idempotencyKey,
            'transaction_id' => $response?->transactionId,
            'failure_code' => $failureCode ?? $response?->failureCode,
            'failure_message' => $failureMessage ?? $response?->failureMessage,
            'payload' => $response !== null ? $response->toArray() : null,
        ]);
    }
}
