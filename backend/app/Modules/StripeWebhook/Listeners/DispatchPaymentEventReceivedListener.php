<?php

namespace App\Modules\StripeWebhook\Listeners;

use App\Modules\Payment\Events\PaymentEventReceived;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

class DispatchPaymentEventReceivedListener implements ShouldQueue
{
    public string $queue;

    public function __construct()
    {
        $this->queue = (string) config('stripe_webhook.queue', 'default');
    }

    public function handle(StripeWebhookProcessed $event): void
    {
        Event::dispatch(new PaymentEventReceived($event->paymentEvent));
    }
}
