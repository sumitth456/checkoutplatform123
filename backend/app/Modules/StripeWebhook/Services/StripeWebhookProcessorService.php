<?php

namespace App\Modules\StripeWebhook\Services;

use App\Models\StripeWebhookEvent;
use App\Modules\StripeWebhook\Contracts\StripeWebhookRepositoryInterface;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessed;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessingFailed;
use App\Modules\StripeWebhook\Exceptions\StripeWebhookException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookProcessorService
{
    public function __construct(
        private readonly StripeWebhookRepositoryInterface $repository,
        private readonly StripeEventNormalizerService $normalizer,
    ) {}

    public function process(int $webhookEventId): StripeWebhookEvent
    {
        $event = $this->repository->findById($webhookEventId);

        if ($event === null) {
            throw StripeWebhookException::eventNotFound($webhookEventId);
        }

        if ($event->status === StripeWebhookProcessingStatus::Processed) {
            return $event;
        }

        $this->repository->markProcessing($event);

        $this->log('info', 'Processing Stripe webhook.', [
            'webhook_uuid' => $event->uuid,
            'stripe_event_id' => $event->stripe_event_id,
            'attempt' => $event->attempt_count + 1,
        ]);

        try {
            $paymentEvent = $this->normalizer->normalize($event->payload);
            $normalized = $paymentEvent->toArray();

            $event = $this->repository->markProcessed($event, $normalized);

            Event::dispatch(new StripeWebhookProcessed($event, $paymentEvent));

            $this->log('info', 'Stripe webhook processed successfully.', [
                'webhook_uuid' => $event->uuid,
                'stripe_event_id' => $event->stripe_event_id,
                'payment_event_type' => $paymentEvent->type->value,
            ]);

            return $event;
        } catch (Throwable $exception) {
            $event = $this->repository->recordAttemptFailure($event, $exception->getMessage());

            $this->log('error', 'Stripe webhook processing failed.', [
                'webhook_uuid' => $event->uuid,
                'stripe_event_id' => $event->stripe_event_id,
                'attempt' => $event->attempt_count,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function markPermanentlyFailed(int $webhookEventId, string $error): StripeWebhookEvent
    {
        $event = $this->repository->findById($webhookEventId);

        if ($event === null) {
            throw StripeWebhookException::eventNotFound($webhookEventId);
        }

        $event = $this->repository->markFailed($event, $error);

        Event::dispatch(new StripeWebhookProcessingFailed($event, $error));

        $this->log('critical', 'Stripe webhook permanently failed after retries.', [
            'webhook_uuid' => $event->uuid,
            'stripe_event_id' => $event->stripe_event_id,
            'attempts' => $event->attempt_count,
            'error' => $error,
        ]);

        return $event;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(config('stripe_webhook.log_channel', 'stack'))->{$level}($message, $context);
    }
}
