<?php

namespace App\Modules\StripeWebhook\Listeners;

use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordStripePaymentLedgerListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly PaymentLedgerInterface $ledger,
    ) {
        $this->queue = (string) config('stripe_webhook.queue', 'default');
    }

    public function handle(StripeWebhookProcessed $event): void
    {
        $this->ledger->recordWebhook(
            provider: PaymentProvider::Stripe,
            externalId: $event->paymentEvent->externalId,
            statusTo: $this->mapEventToStatus($event->paymentEvent->type),
            payload: array_merge($event->paymentEvent->toArray(), [
                'stripe_webhook_uuid' => $event->webhookEvent->uuid,
                'stripe_event_id' => $event->webhookEvent->stripe_event_id,
            ]),
        );
    }

    private function mapEventToStatus(PaymentEventType $type): PaymentStatus
    {
        return match ($type) {
            PaymentEventType::Authorized => PaymentStatus::Authorized,
            PaymentEventType::Captured => PaymentStatus::Captured,
            PaymentEventType::Refunded => PaymentStatus::Refunded,
            PaymentEventType::Voided => PaymentStatus::Voided,
            PaymentEventType::Failed, PaymentEventType::Disputed => PaymentStatus::Failed,
        };
    }
}
