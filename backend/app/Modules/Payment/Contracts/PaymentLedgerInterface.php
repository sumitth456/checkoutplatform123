<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

interface PaymentLedgerInterface
{
    public function record(
        LedgerEntryType $entryType,
        PaymentProvider $provider,
        PaymentStatus $statusTo,
        PaymentResponse $response,
        ?PaymentStatus $statusFrom = null,
        ?string $idempotencyKey = null,
        ?int $transactionId = null,
    ): void;

    public function recordWebhook(
        PaymentProvider $provider,
        string $externalId,
        PaymentStatus $statusTo,
        array $payload,
    ): void;
}
