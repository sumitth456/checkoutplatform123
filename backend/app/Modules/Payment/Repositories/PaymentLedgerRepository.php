<?php

namespace App\Modules\Payment\Repositories;

use App\Models\PaymentLedgerEntry;
use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

class PaymentLedgerRepository implements PaymentLedgerInterface
{
    public function record(
        LedgerEntryType $entryType,
        PaymentProvider $provider,
        PaymentStatus $statusTo,
        PaymentResponse $response,
        ?PaymentStatus $statusFrom = null,
        ?string $idempotencyKey = null,
        ?int $transactionId = null,
    ): void {
        PaymentLedgerEntry::query()->create([
            'transaction_id' => $transactionId,
            'provider' => $provider,
            'entry_type' => $entryType,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'amount' => $response->amount,
            'currency' => $response->currency,
            'external_id' => $response->gatewayReference,
            'idempotency_key' => $idempotencyKey,
            'payload' => [
                'transaction_id' => $response->transactionId,
                'gateway_reference' => $response->gatewayReference,
                'raw' => $response->raw,
                'metadata' => $response->metadata,
            ],
        ]);
    }

    public function recordWebhook(
        PaymentProvider $provider,
        string $externalId,
        PaymentStatus $statusTo,
        array $payload,
    ): void {
        PaymentLedgerEntry::query()->create([
            'provider' => $provider,
            'entry_type' => LedgerEntryType::Webhook,
            'status_to' => $statusTo,
            'external_id' => $externalId,
            'payload' => $payload,
        ]);
    }
}
