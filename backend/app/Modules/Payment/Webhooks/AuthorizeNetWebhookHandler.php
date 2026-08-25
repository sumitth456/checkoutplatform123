<?php

namespace App\Modules\Payment\Webhooks;

use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;

class AuthorizeNetWebhookHandler extends AbstractWebhookHandler
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::AuthorizeNet;
    }

    protected function mapEventType(array $payload): PaymentEventType
    {
        return match ($payload['eventType'] ?? '') {
            'net.authorize.payment.authcapture.created' => PaymentEventType::Captured,
            'net.authorize.payment.refund.created' => PaymentEventType::Refunded,
            'net.authorize.payment.void.created' => PaymentEventType::Voided,
            default => PaymentEventType::Authorized,
        };
    }

    protected function mapExternalId(array $payload): string
    {
        return (string) data_get($payload, 'payload.id', 'unknown');
    }

    protected function mapStatus(array $payload): string
    {
        return (string) data_get($payload, 'payload.authAmount.status', 'pending');
    }

    protected function mapAmount(array $payload): ?string
    {
        return data_get($payload, 'payload.authAmount.amount');
    }

    protected function mapCurrency(array $payload): ?string
    {
        return data_get($payload, 'payload.authAmount.currency', 'USD');
    }
}
