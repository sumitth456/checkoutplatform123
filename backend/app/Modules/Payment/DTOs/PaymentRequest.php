<?php

namespace App\Modules\Payment\DTOs;

use App\Modules\Payment\Enums\PaymentProvider;

readonly class PaymentRequest
{
    public function __construct(
        public string $amount,
        public string $currency,
        public string $paymentToken,
        public ?string $merchantId = null,
        public ?string $orderId = null,
        public ?string $customerId = null,
        public ?string $description = null,
        public ?PaymentProvider $provider = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {
        $this->assertTokenizedPaymentReference($paymentToken);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (string) $data['amount'],
            currency: strtoupper((string) $data['currency']),
            paymentToken: (string) $data['payment_token'],
            merchantId: $data['merchant_id'] ?? null,
            orderId: $data['order_id'] ?? null,
            customerId: $data['customer_id'] ?? null,
            description: $data['description'] ?? null,
            provider: isset($data['provider']) ? PaymentProvider::from($data['provider']) : null,
            idempotencyKey: $data['idempotency_key'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_token' => $this->paymentToken,
            'merchant_id' => $this->merchantId,
            'order_id' => $this->orderId,
            'customer_id' => $this->customerId,
            'description' => $this->description,
            'provider' => $this->provider?->value,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
        ];
    }

    private function assertTokenizedPaymentReference(string $paymentToken): void
    {
        if (preg_match('/^\d{13,19}$/', preg_replace('/\D/', '', $paymentToken)) === 1) {
            throw new \InvalidArgumentException('Raw card numbers are not permitted. Use a provider payment token.');
        }
    }
}
