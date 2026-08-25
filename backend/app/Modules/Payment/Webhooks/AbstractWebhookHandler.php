<?php

namespace App\Modules\Payment\Webhooks;

use App\Modules\Payment\Contracts\IWebhookHandler;
use App\Modules\Payment\DTOs\PaymentEvent;
use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use Illuminate\Http\Request;

abstract class AbstractWebhookHandler implements IWebhookHandler
{
    abstract protected function mapEventType(array $payload): PaymentEventType;

    abstract protected function mapExternalId(array $payload): string;

    abstract protected function mapStatus(array $payload): string;

    abstract protected function mapAmount(array $payload): ?string;

    abstract protected function mapCurrency(array $payload): ?string;

    public function handle(Request $request): PaymentEvent
    {
        $payload = $request->all();

        return new PaymentEvent(
            type: $this->mapEventType($payload),
            provider: $this->provider(),
            externalId: $this->mapExternalId($payload),
            status: $this->mapStatus($payload),
            amount: $this->mapAmount($payload),
            currency: $this->mapCurrency($payload),
            payload: $payload,
        );
    }

    protected function mapPaymentStatus(string $status): PaymentStatus
    {
        return match (strtolower($status)) {
            'authorized', 'approved' => PaymentStatus::Authorized,
            'captured', 'completed', 'succeeded' => PaymentStatus::Captured,
            'refunded' => PaymentStatus::Refunded,
            'voided', 'canceled' => PaymentStatus::Voided,
            'failed', 'declined' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }
}
