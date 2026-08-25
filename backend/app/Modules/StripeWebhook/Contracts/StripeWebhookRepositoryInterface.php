<?php

namespace App\Modules\StripeWebhook\Contracts;

use App\Models\StripeWebhookEvent;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;

interface StripeWebhookRepositoryInterface
{
    public function findByStripeEventId(string $stripeEventId): ?StripeWebhookEvent;

    public function findById(int $id): ?StripeWebhookEvent;

    public function createIncoming(string $stripeEventId, string $eventType, array $payload): StripeWebhookEvent;

    public function markDuplicate(StripeWebhookEvent $event): StripeWebhookEvent;

    public function markProcessing(StripeWebhookEvent $event): StripeWebhookEvent;

    public function markProcessed(StripeWebhookEvent $event, array $normalizedPayload): StripeWebhookEvent;

    public function markFailed(StripeWebhookEvent $event, string $error): StripeWebhookEvent;

    public function recordAttemptFailure(StripeWebhookEvent $event, string $error): StripeWebhookEvent;
}
