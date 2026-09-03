<?php

namespace App\Modules\Email\Listeners;

use App\Modules\Email\Enums\EmailTrigger;
use App\Modules\Email\Services\EmailAutomationService;
use App\Modules\Order\Services\OrderTestDetector;
use App\Modules\Order\Events\CheckoutPaymentFailed;
use App\Modules\Order\Events\FulfillmentRecordCreated;
use App\Modules\Order\Events\FulfillmentSubmittedToWarehouse;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Events\OrderPlaced;
use App\Modules\Subscription\Events\SubscriptionCancelled;
use App\Modules\Subscription\Events\SubscriptionCreated;
use App\Modules\Subscription\Events\SubscriptionPaused;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Events\SubscriptionRebilled;
use App\Modules\Subscription\Events\SubscriptionResumed;
use App\Modules\Subscription\Events\SubscriptionRetryScheduled;
use Illuminate\Contracts\Queue\ShouldQueue;

final class DispatchEmailAutomationListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly EmailAutomationService $automations,
        private readonly OrderTestDetector $testOrders,
    ) {
        $this->queue = (string) config('email.queue', 'default');
    }

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $this->automations->trigger(EmailTrigger::OrderPlaced, $event->order);
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        $this->automations->trigger(EmailTrigger::OrderPaid, $event->order);
        $this->automations->trigger(EmailTrigger::PaymentSuccess, $event->order);
    }

    public function handlePaymentFailed(CheckoutPaymentFailed $event): void
    {
        $order = $event->failure->order;

        if ($order !== null) {
            $this->automations->trigger(EmailTrigger::PaymentFailed, $order);
        }
    }

    public function handleFulfillmentCreated(FulfillmentRecordCreated $event): void
    {
        $order = $event->fulfillment->order;

        if ($order !== null) {
            $this->automations->trigger(EmailTrigger::OrderProcessing, $order);
            $this->automations->trigger(EmailTrigger::ShipmentCreated, $order);
        }
    }

    public function handleFulfillmentSubmitted(FulfillmentSubmittedToWarehouse $event): void
    {
        $order = $event->fulfillment->order;

        if ($order === null) {
            return;
        }

        if ($event->response->skipCustomerNotification || $event->response->shipmentOnly) {
            return;
        }

        if ($this->testOrders->isTestOrder($order)) {
            return;
        }

        if (blank($event->response->trackingNumber)) {
            return;
        }

        $metadata = $order->metadata ?? [];
        $metadata['tracking_number'] = $event->response->trackingNumber ?? ($metadata['tracking_number'] ?? null);
        $metadata['tracking_url'] = $event->response->trackingUrl ?? ($metadata['tracking_url'] ?? null);
        $metadata['carrier'] = $event->response->carrier ?? ($metadata['carrier'] ?? null);
        $order->forceFill(['metadata' => $metadata])->save();

        $freshOrder = $order->fresh();
        $this->automations->trigger(EmailTrigger::OrderShipped, $freshOrder);
        $this->automations->trigger(EmailTrigger::TrackingEmail, $freshOrder);
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->automations->trigger(EmailTrigger::SubscriptionCreated, $event->subscription);
    }

    public function handleSubscriptionRebilled(SubscriptionRebilled $event): void
    {
        $this->automations->trigger(EmailTrigger::RebillSuccess, $event->subscription);
        $this->automations->trigger(EmailTrigger::SubscriptionRenewed, $event->subscription);
    }

    public function handleSubscriptionRebillFailed(SubscriptionRebillFailed $event): void
    {
        $this->automations->trigger(EmailTrigger::RebillFailed, $event->subscription);
    }

    public function handleSubscriptionRetryScheduled(SubscriptionRetryScheduled $event): void
    {
        $this->automations->trigger(EmailTrigger::RetryScheduled, $event->subscription);
    }

    public function handleSubscriptionCancelled(SubscriptionCancelled $event): void
    {
        $this->automations->trigger(EmailTrigger::SubscriptionCancelled, $event->subscription);
    }

    public function handleSubscriptionPaused(SubscriptionPaused $event): void
    {
        $this->automations->trigger(EmailTrigger::SubscriptionPaused, $event->subscription);
    }

    public function handleSubscriptionResumed(SubscriptionResumed $event): void
    {
        $this->automations->trigger(EmailTrigger::SubscriptionResumed, $event->subscription);
    }
}
