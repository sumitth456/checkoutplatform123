<?php

namespace App\Modules\Order\Listeners;

use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Services\CheckoutSubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateSubscriptionsFromOrderListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly CheckoutSubscriptionService $subscriptions,
    ) {
        $this->queue = (string) config('checkout.queue', 'default');
    }

    public function handle(OrderPaid $event): void
    {
        $paymentToken = (string) (
            $event->order->metadata['payment_token']
            ?? $event->order->metadata['payment_intent_id']
            ?? $event->paymentResponse->transactionId
        );

        $this->subscriptions->createFromOrder(
            order: $event->order,
            paymentToken: $paymentToken,
            paymentProvider: $event->paymentResponse->provider->value,
            paymentMethodType: ($event->order->payment_method_type ?? $event->order->metadata['payment_method'] ?? null) === 'ach'
                ? 'ach'
                : 'card',
        );
    }
}
