<?php

namespace App\Modules\Payment\Webhooks;

use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;

class NMIWebhookHandler extends AbstractWebhookHandler
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Nmi;
    }

    protected function mapEventType(array $payload): PaymentEventType
    {
        return match ($payload['event_type'] ?? '') {
            'sale' => PaymentEventType::Captured,
            'refund' => PaymentEventType::Refunded,
            'void' => PaymentEventType::Voided,
            default => PaymentEventType::Authorized,
        };
    }

    protected function mapExternalId(array $payload): string
    {
        return (string) ($payload['transaction_id'] ?? 'unknown');
    }

    protected function mapStatus(array $payload): string
    {
        return (string) ($payload['condition'] ?? 'pending');
    }

    protected function mapAmount(array $payload): ?string
    {
        return $payload['amount'] ?? null;
    }

    protected function mapCurrency(array $payload): ?string
    {
        return $payload['currency'] ?? 'USD';
    }
}
