<?php

namespace App\Modules\Order\DTOs;

use App\Modules\Payment\Enums\PaymentProvider;

readonly class CheckoutPaymentInputDTO
{
    public function __construct(
        public string $paymentToken,
        public string $idempotencyKey,
        public ?PaymentProvider $provider = null,
        public ?string $paymentIntentId = null,
        public string $paymentMethod = 'card',
        public ?string $achProvider = null,
    ) {}

    public function isAch(): bool
    {
        return $this->paymentMethod === 'ach';
    }

    public static function fromValidated(array $data): self
    {
        return new self(
            paymentToken: (string) $data['payment_token'],
            idempotencyKey: (string) $data['idempotency_key'],
            provider: isset($data['provider']) ? PaymentProvider::from($data['provider']) : null,
            paymentIntentId: isset($data['payment_intent_id']) ? (string) $data['payment_intent_id'] : null,
            paymentMethod: (string) ($data['payment_method'] ?? 'card'),
            achProvider: isset($data['ach_provider']) ? (string) $data['ach_provider'] : null,
        );
    }
}
