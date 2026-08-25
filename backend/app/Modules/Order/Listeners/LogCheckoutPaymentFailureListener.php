<?php

namespace App\Modules\Order\Listeners;

use App\Modules\Order\Events\CheckoutPaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogCheckoutPaymentFailureListener implements ShouldQueue
{
    public string $queue;

    public function __construct()
    {
        $this->queue = (string) config('checkout.queue', 'default');
    }

    public function handle(CheckoutPaymentFailed $event): void
    {
        Log::warning('checkout.payment_failed', [
            'failure_uuid' => $event->failure->uuid,
            'idempotency_key' => $event->idempotencyKey,
            'customer_email' => $event->failure->customer_email,
            'payment_intent_id' => $event->failure->payment_intent_id,
            'failure_code' => $event->failure->failure_code,
            'failure_message' => $event->failure->failure_message,
            'provider' => $event->exception->provider->value,
        ]);
    }
}
