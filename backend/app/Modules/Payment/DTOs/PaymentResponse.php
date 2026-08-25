<?php

namespace App\Modules\Payment\DTOs;

use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

readonly class PaymentResponse
{
    public function __construct(
        public PaymentStatus $status,
        public string $transactionId,
        public string $gatewayReference,
        public PaymentProvider $provider,
        public string $amount,
        public string $currency,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public array $raw = [],
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'transaction_id' => $this->transactionId,
            'gateway_reference' => $this->gatewayReference,
            'provider' => $this->provider->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: PaymentStatus::from($data['status']),
            transactionId: $data['transaction_id'],
            gatewayReference: $data['gateway_reference'],
            provider: PaymentProvider::from($data['provider']),
            amount: (string) $data['amount'],
            currency: (string) $data['currency'],
            failureCode: $data['failure_code'] ?? null,
            failureMessage: $data['failure_message'] ?? null,
            raw: $data['raw'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }
}
