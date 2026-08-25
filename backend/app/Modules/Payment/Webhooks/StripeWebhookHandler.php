<?php

namespace App\Modules\Payment\Webhooks;

use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;

class StripeWebhookHandler extends AbstractWebhookHandler
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    protected function mapEventType(array $payload): PaymentEventType
    {
        return match ($payload['type'] ?? '') {
            'payment_intent.succeeded' => PaymentEventType::Captured,
            'payment_intent.payment_failed' => PaymentEventType::Failed,
            'charge.refunded' => PaymentEventType::Refunded,
            'payment_intent.canceled' => PaymentEventType::Voided,
            default => PaymentEventType::Authorized,
        };
    }

    protected function mapExternalId(array $payload): string
    {
        return (string) data_get($payload, 'data.object.id', data_get($payload, 'id', 'unknown'));
    }

    protected function mapStatus(array $payload): string
    {
        return (string) data_get($payload, 'data.object.status', 'pending');
    }

    protected function mapAmount(array $payload): ?string
    {
        $amount = data_get($payload, 'data.object.amount');

        return $amount !== null ? number_format(((int) $amount) / 100, 2, '.', '') : null;
    }

    protected function mapCurrency(array $payload): ?string
    {
        $currency = data_get($payload, 'data.object.currency');

        return $currency !== null ? strtoupper((string) $currency) : null;
    }
}
