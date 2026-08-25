<?php

namespace App\Modules\Subscription\Listeners;

use App\Models\Subscription;
use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Events\PaymentEventReceived;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Services\DunningService;
use App\Modules\Subscription\Services\FailedPaymentRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReconcileSubscriptionPaymentWebhookListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly FailedPaymentRetryService $retryService,
        private readonly DunningService $dunning,
    ) {
        $this->queue = (string) config('subscription.queues.webhooks', 'default');
    }

    public function handle(PaymentEventReceived $event): void
    {
        if ($event->event->provider !== PaymentProvider::Stripe) {
            return;
        }

        $subscriptionUuid = data_get($event->event->payload, 'data.object.metadata.subscription_id');

        if (blank($subscriptionUuid)) {
            return;
        }

        $subscription = Subscription::query()
            ->where('uuid', $subscriptionUuid)
            ->first();

        if ($subscription === null) {
            return;
        }

        if ($event->event->type === PaymentEventType::Failed) {
            if ($subscription->status !== SubscriptionStatus::Cancelled) {
                $subscription->status = SubscriptionStatus::PastDue;
                $subscription->save();
                $this->dunning->schedule($subscription->fresh(['plan.dunningPolicy']));
            }

            return;
        }

        if ($event->event->type === PaymentEventType::Captured) {
            $this->dunning->reset($subscription);
            if ($subscription->status === SubscriptionStatus::PastDue) {
                $subscription->status = SubscriptionStatus::Active;
                $subscription->retry_count = 0;
                $subscription->save();
            }
        }
    }
}
