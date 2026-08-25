<?php

namespace App\Modules\Order\Listeners;

use App\Modules\Affiliate\Services\AffiliateConversionService;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Order\Actions\CreateRebillOrderAction;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Events\SubscriptionRebilled;

class CreateRebillOrderListener
{
    public function __construct(
        private readonly CreateRebillOrderAction $createRebillOrder,
        private readonly AffiliateConversionService $affiliateConversions,
        private readonly FulfillmentOrchestratorService $fulfillmentOrchestrator,
    ) {}

    public function handleRebilled(SubscriptionRebilled $event): void
    {
        if ($event->ledgerEntry === null) {
            return;
        }

        $order = $this->createRebillOrder->execute($event->subscription, $event->ledgerEntry);

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            $this->affiliateConversions->recordOrderConversion($order);
            $this->fulfillmentOrchestrator->createFromRebillOrder($order->fresh(['items.product', 'parentOrder.items.product']));
        }
    }

    public function handleRebillFailed(SubscriptionRebillFailed $event): void
    {
        if ($event->ledgerEntry === null) {
            return;
        }

        $this->createRebillOrder->execute($event->subscription, $event->ledgerEntry);
    }
}
