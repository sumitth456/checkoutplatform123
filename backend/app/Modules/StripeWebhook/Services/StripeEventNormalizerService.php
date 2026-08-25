<?php

namespace App\Modules\StripeWebhook\Services;

use App\Modules\Payment\DTOs\PaymentEvent;
use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\StripeWebhook\Exceptions\StripeWebhookException;

class StripeEventNormalizerService
{
    public function normalize(array $payload): PaymentEvent
    {
        $eventType = (string) ($payload['type'] ?? 'unknown');

        return new PaymentEvent(
            type: $this->mapEventType($eventType),
            provider: PaymentProvider::Stripe,
            externalId: $this->mapExternalId($payload),
            status: $this->mapStatus($payload),
            amount: $this->mapAmount($payload),
            currency: $this->mapCurrency($payload),
            payload: $payload,
        );
    }

    private function mapEventType(string $stripeType): PaymentEventType
    {
        return match ($stripeType) {
            'payment_intent.succeeded' => PaymentEventType::Captured,
            'payment_intent.payment_failed' => PaymentEventType::Failed,
            'charge.refunded' => PaymentEventType::Refunded,
            'payment_intent.canceled' => PaymentEventType::Voided,
            'charge.dispute.created' => PaymentEventType::Disputed,
            default => PaymentEventType::Authorized,
        };
    }

    private function mapExternalId(array $payload): string
    {
        return (string) data_get($payload, 'data.object.id', data_get($payload, 'id', 'unknown'));
    }

    private function mapStatus(array $payload): string
    {
        return (string) data_get($payload, 'data.object.status', 'pending');
    }

    private function mapAmount(array $payload): ?string
    {
        $amount = data_get($payload, 'data.object.amount');

        return $amount !== null ? number_format(((int) $amount) / 100, 2, '.', '') : null;
    }

    private function mapCurrency(array $payload): ?string
    {
        $currency = data_get($payload, 'data.object.currency');

        return $currency !== null ? strtoupper((string) $currency) : null;
    }
}
