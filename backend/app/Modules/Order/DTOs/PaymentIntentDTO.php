<?php

namespace App\Modules\Order\DTOs;

readonly class PaymentIntentDTO
{
    public function __construct(
        public string $id,
        public string $clientSecret,
        public string $status,
        public string $amount,
        public string $currency,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_secret' => $this->clientSecret,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
