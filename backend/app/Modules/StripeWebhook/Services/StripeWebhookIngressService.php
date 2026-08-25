<?php

namespace App\Modules\StripeWebhook\Services;

use App\Models\StripeWebhookEvent;
use App\Modules\StripeWebhook\Contracts\StripeWebhookRepositoryInterface;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;
use App\Modules\StripeWebhook\Events\StripeWebhookReceived;
use App\Modules\StripeWebhook\Exceptions\StripeWebhookException;
use App\Modules\StripeWebhook\Jobs\ProcessStripeWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class StripeWebhookIngressService
{
    public function __construct(
        private readonly StripeSignatureVerifierService $signatureVerifier,
        private readonly StripeWebhookRepositoryInterface $repository,
    ) {}

    public function receive(Request $request): StripeWebhookEvent
    {
        $this->log('info', 'Stripe webhook received.', [
            'content_length' => strlen($request->getContent()),
        ]);

        $this->signatureVerifier->verify($request);

        $payload = $request->json()->all();

        if (! is_array($payload) || blank($payload['id'] ?? null) || blank($payload['type'] ?? null)) {
            throw StripeWebhookException::invalidPayload('Missing Stripe event id or type.');
        }

        $existing = $this->repository->findByStripeEventId((string) $payload['id']);

        if ($existing !== null && in_array($existing->status, [
            StripeWebhookProcessingStatus::Processed,
            StripeWebhookProcessingStatus::Processing,
            StripeWebhookProcessingStatus::Pending,
        ], true)) {
            $this->log('info', 'Duplicate Stripe webhook ignored.', [
                'stripe_event_id' => $payload['id'],
                'status' => $existing->status->value,
            ]);

            return $this->repository->markDuplicate($existing);
        }

        $event = $this->repository->createIncoming(
            stripeEventId: (string) $payload['id'],
            eventType: (string) $payload['type'],
            payload: $payload,
        );

        Event::dispatch(new StripeWebhookReceived($event));

        ProcessStripeWebhookJob::dispatch($event->id)
            ->onQueue((string) config('stripe_webhook.queue', 'default'));

        $this->log('info', 'Stripe webhook queued for processing.', [
            'webhook_uuid' => $event->uuid,
            'stripe_event_id' => $event->stripe_event_id,
            'event_type' => $event->event_type,
        ]);

        return $event;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(config('stripe_webhook.log_channel', 'stack'))->{$level}($message, $context);
    }
}
