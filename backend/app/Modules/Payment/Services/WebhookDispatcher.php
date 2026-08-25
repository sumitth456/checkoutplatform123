<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Contracts\IWebhookHandler;
use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\DTOs\PaymentEvent;
use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentEventReceived;
use App\Modules\Payment\Webhooks\AuthorizeNetWebhookHandler;
use App\Modules\Payment\Webhooks\NMIWebhookHandler;
use App\Modules\Payment\Webhooks\PayPalWebhookHandler;
use App\Modules\Payment\Webhooks\StripeWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class WebhookDispatcher
{
    /** @var array<string, IWebhookHandler> */
    private array $handlers;

    public function __construct(
        private readonly PaymentLedgerInterface $ledger,
    ) {
        $this->handlers = [
            PaymentProvider::Stripe->value => app(StripeWebhookHandler::class),
            PaymentProvider::PayPal->value => app(PayPalWebhookHandler::class),
            PaymentProvider::AuthorizeNet->value => app(AuthorizeNetWebhookHandler::class),
            PaymentProvider::Nmi->value => app(NMIWebhookHandler::class),
        ];
    }

    public function dispatch(PaymentProvider $provider, Request $request): PaymentEvent
    {
        $handler = $this->handlers[$provider->value] ?? null;

        if ($handler === null) {
            throw new InvalidArgumentException("No webhook handler registered for [{$provider->value}].");
        }

        $event = $handler->handle($request);

        $this->ledger->recordWebhook(
            provider: $provider,
            externalId: $event->externalId,
            statusTo: $this->mapEventToStatus($event),
            payload: $event->toArray(),
        );

        Event::dispatch(new PaymentEventReceived($event));

        return $event;
    }

    private function mapEventToStatus(PaymentEvent $event): PaymentStatus
    {
        return match ($event->type) {
            PaymentEventType::Authorized => PaymentStatus::Authorized,
            PaymentEventType::Captured => PaymentStatus::Captured,
            PaymentEventType::Refunded => PaymentStatus::Refunded,
            PaymentEventType::Voided => PaymentStatus::Voided,
            PaymentEventType::Failed => PaymentStatus::Failed,
            PaymentEventType::Disputed => PaymentStatus::Failed,
        };
    }
}
