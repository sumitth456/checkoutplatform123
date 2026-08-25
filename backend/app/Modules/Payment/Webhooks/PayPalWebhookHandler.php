<?php

namespace App\Modules\Payment\Webhooks;

use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;

class PayPalWebhookHandler extends AbstractWebhookHandler
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::PayPal;
    }

    protected function mapEventType(array $payload): PaymentEventType
    {
        return match ($payload['event_type'] ?? '') {
            'PAYMENT.CAPTURE.COMPLETED' => PaymentEventType::Captured,
            'PAYMENT.CAPTURE.DENIED' => PaymentEventType::Failed,
            'PAYMENT.CAPTURE.REFUNDED' => PaymentEventType::Refunded,
            default => PaymentEventType::Authorized,
        };
    }

    protected function mapExternalId(array $payload): string
    {
        return (string) data_get($payload, 'resource.id', 'unknown');
    }

    protected function mapStatus(array $payload): string
    {
        return (string) data_get($payload, 'resource.status', 'PENDING');
    }

    protected function mapAmount(array $payload): ?string
    {
        return data_get($payload, 'resource.amount.value');
    }

    protected function mapCurrency(array $payload): ?string
    {
        return data_get($payload, 'resource.amount.currency_code');
    }
}
