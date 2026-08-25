<?php

namespace App\Modules\StripeWebhook\Listeners;

use App\Modules\StripeWebhook\Events\StripeWebhookProcessed;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessingFailed;
use App\Modules\StripeWebhook\Events\StripeWebhookReceived;
use Illuminate\Support\Facades\Log;

class LogStripeWebhookListener
{
    public function handleReceived(StripeWebhookReceived $event): void
    {
        $this->log('info', 'Stripe webhook stored.', [
            'webhook_uuid' => $event->webhookEvent->uuid,
            'stripe_event_id' => $event->webhookEvent->stripe_event_id,
            'event_type' => $event->webhookEvent->event_type,
        ]);
    }

    public function handleProcessed(StripeWebhookProcessed $event): void
    {
        $this->log('info', 'Stripe webhook side effects dispatched.', [
            'webhook_uuid' => $event->webhookEvent->uuid,
            'payment_event_type' => $event->paymentEvent->type->value,
            'external_id' => $event->paymentEvent->externalId,
        ]);
    }

    public function handleFailed(StripeWebhookProcessingFailed $event): void
    {
        $this->log('error', 'Stripe webhook exhausted retries.', [
            'webhook_uuid' => $event->webhookEvent->uuid,
            'stripe_event_id' => $event->webhookEvent->stripe_event_id,
            'attempts' => $event->webhookEvent->attempt_count,
            'error' => $event->error,
        ]);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(config('stripe_webhook.log_channel', 'stack'))->{$level}($message, $context);
    }
}
