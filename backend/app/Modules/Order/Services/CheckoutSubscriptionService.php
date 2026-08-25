<?php

namespace App\Modules\Order\Services;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Campaign;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Services\SubscriptionLifecycleService;

class CheckoutSubscriptionService
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptions,
    ) {}

    /**
     * @return list<Subscription>
     */
    public function createFromOrder(
        Order $order,
        string $paymentToken,
        ?string $paymentProvider = null,
        ?string $paymentMethodType = null,
    ): array {
        $existing = Subscription::query()
            ->where('order_id', $order->id)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $planUuid = $order->metadata['subscription_plan_uuid'] ?? null;

        if (blank($planUuid) && filled($order->platform_campaign_id)) {
            $planUuid = Campaign::query()
                ->with('subscriptionPlan')
                ->find($order->platform_campaign_id)
                ?->subscriptionPlan
                ?->uuid;
        }

        if (blank($planUuid)) {
            return [];
        }

        $order->loadMissing('items');

        $plan = SubscriptionPlan::query()
            ->with('steps')
            ->where('uuid', $planUuid)
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            return [];
        }

        $firstRebillStep = $plan->steps->firstWhere('step_order', 1);
        $nextAmount = $firstRebillStep !== null ? $firstRebillStep->amount : $order->grand_total;

        $subscriptionLine = $order->items->first(fn ($item) => $item->is_subscription)
            ?? $order->items->first();

        $subscription = $this->subscriptions->createFromPlan(
            customerId: (int) $order->customer_id,
            plan: $plan,
            paymentToken: $paymentToken,
            paymentProvider: $paymentProvider,
            attributes: [
                'order_id' => $order->id,
                'order_item_id' => $subscriptionLine?->id,
                'product_id' => $subscriptionLine?->product_id,
                'variant_id' => $subscriptionLine?->variant_id,
                'platform_campaign_id' => $order->platform_campaign_id,
                'amount' => $nextAmount,
                'payment_method_type' => $paymentMethodType ?? ($order->payment_method_type ?? 'card'),
                'rebill_queue_status' => RebillQueueStatus::Queued,
                'metadata' => [
                    'order_uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'payment_method_id' => $order->metadata['payment_method_id'] ?? $paymentToken,
                    'stripe_customer_id' => $order->metadata['stripe_customer_id'] ?? null,
                    'initial_cycle_paid_via_order' => true,
                ],
            ],
        );

        return [$subscription->fresh(['plan.steps'])];
    }
}
