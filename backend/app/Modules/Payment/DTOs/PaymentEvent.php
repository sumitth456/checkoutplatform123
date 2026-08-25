<?php

namespace App\Modules\Payment\DTOs;

use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;

readonly class PaymentEvent
{
    public function __construct(
        public PaymentEventType $type,
        public PaymentProvider $provider,
        public string $externalId,
        public string $status,
        public ?string $amount = null,
        public ?string $currency = null,
        public array $payload = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'provider' => $this->provider->value,
            'external_id' => $this->externalId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payload' => $this->payload,
        ];
    }
}
