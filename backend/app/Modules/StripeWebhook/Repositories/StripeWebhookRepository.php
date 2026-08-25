<?php

namespace App\Modules\StripeWebhook\Repositories;

use App\Models\StripeWebhookEvent;
use App\Modules\StripeWebhook\Contracts\StripeWebhookRepositoryInterface;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;

class StripeWebhookRepository implements StripeWebhookRepositoryInterface
{
    public function findByStripeEventId(string $stripeEventId): ?StripeWebhookEvent
    {
        return StripeWebhookEvent::query()
            ->where('stripe_event_id', $stripeEventId)
            ->first();
    }

    public function findById(int $id): ?StripeWebhookEvent
    {
        return StripeWebhookEvent::query()->find($id);
    }

    public function createIncoming(string $stripeEventId, string $eventType, array $payload): StripeWebhookEvent
    {
        return StripeWebhookEvent::query()->create([
            'stripe_event_id' => $stripeEventId,
            'event_type' => $eventType,
            'status' => StripeWebhookProcessingStatus::Pending,
            'payload' => $payload,
        ]);
    }

    public function markDuplicate(StripeWebhookEvent $event): StripeWebhookEvent
    {
        $event->status = StripeWebhookProcessingStatus::Duplicate;
        $event->processed_at = now();
        $event->save();

        return $event;
    }

    public function markProcessing(StripeWebhookEvent $event): StripeWebhookEvent
    {
        $event->status = StripeWebhookProcessingStatus::Processing;
        $event->save();

        return $event;
    }

    public function markProcessed(StripeWebhookEvent $event, array $normalizedPayload): StripeWebhookEvent
    {
        $event->status = StripeWebhookProcessingStatus::Processed;
        $event->normalized_payload = $normalizedPayload;
        $event->processed_at = now();
        $event->last_error = null;
        $event->save();

        return $event;
    }

    public function markFailed(StripeWebhookEvent $event, string $error): StripeWebhookEvent
    {
        $event->status = StripeWebhookProcessingStatus::Failed;
        $event->last_error = $error;
        $event->processed_at = now();
        $event->save();

        return $event;
    }

    public function recordAttemptFailure(StripeWebhookEvent $event, string $error): StripeWebhookEvent
    {
        $event->attempt_count++;
        $event->last_error = $error;
        $event->save();

        return $event;
    }
}
