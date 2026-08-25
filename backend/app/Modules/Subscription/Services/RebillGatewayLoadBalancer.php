<?php

namespace App\Modules\Subscription\Services;

use App\Models\PaymentGatewayConfig;
use App\Models\Subscription;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Support\Collection;

class RebillGatewayLoadBalancer
{
    public function __construct(
        private readonly PaymentGatewayLoadService $load,
    ) {}

    /**
     * Gateways to attempt for this rebill charge, in order (priority, then weight).
     * Retries rotate the starting gateway so the next priority path is used after a failed cycle.
     *
     * @return list<PaymentGatewayConfig>
     */
    public function gatewaysForCharge(Subscription $subscription): array
    {
        $subscription->loadMissing('plan.paymentGateway');

        $eligible = $this->eligibleGateways();

        if ($eligible->isEmpty()) {
            return [];
        }

        $ordered = $this->applyLoadBalancingSelection($eligible, $subscription);

        $planGateway = $subscription->plan?->paymentGateway;

        if ($planGateway !== null
            && $planGateway->is_active
            && $planGateway->supports_subscriptions
            && $this->load->isWithinLimits($planGateway)) {
            $ordered = array_values(array_filter(
                $ordered,
                fn (PaymentGatewayConfig $gateway): bool => $gateway->id !== $planGateway->id,
            ));
            array_unshift($ordered, $planGateway);
        }

        $rotation = max(0, (int) $subscription->retry_count) % max(1, count($ordered));

        if ($rotation === 0) {
            return $ordered;
        }

        return array_merge(
            array_slice($ordered, $rotation),
            array_slice($ordered, 0, $rotation),
        );
    }

    /**
     * @return Collection<int, PaymentGatewayConfig>
     */
    private function eligibleGateways(): Collection
    {
        $query = PaymentGatewayConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('weight');

        $gateways = $query->get()->filter(
            fn (PaymentGatewayConfig $gateway): bool => $this->load->isWithinLimits($gateway),
        );

        $subscriptionCapable = $gateways->filter(
            fn (PaymentGatewayConfig $gateway): bool => (bool) $gateway->supports_subscriptions,
        );

        if ($subscriptionCapable->isNotEmpty()) {
            return $subscriptionCapable->values();
        }

        return $gateways->values();
    }

    /**
     * @param  Collection<int, PaymentGatewayConfig>  $eligible
     * @return list<PaymentGatewayConfig>
     */
    private function applyLoadBalancingSelection(Collection $eligible, Subscription $subscription): array
    {
        $grouped = $eligible->groupBy('sort_order');
        $result = [];

        foreach ($grouped->sortKeys() as $priority => $gatewaysAtPriority) {
            /** @var Collection<int, PaymentGatewayConfig> $gatewaysAtPriority */
            $gatewaysAtPriority = $gatewaysAtPriority->values();

            if ($gatewaysAtPriority->count() === 1) {
                $result[] = $gatewaysAtPriority->first();

                continue;
            }

            $selected = $this->weightedPick($gatewaysAtPriority, $subscription, (int) $priority);
            $result[] = $selected;

            foreach ($gatewaysAtPriority as $gateway) {
                if ($gateway->id !== $selected->id && $gateway->failover_enabled) {
                    $result[] = $gateway;
                }
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, PaymentGatewayConfig>  $gateways
     */
    private function weightedPick(Collection $gateways, Subscription $subscription, int $priority): PaymentGatewayConfig
    {
        $totalWeight = $gateways->sum(fn (PaymentGatewayConfig $g): int => max(1, (int) $g->weight));

        if ($totalWeight <= 0) {
            return $gateways->first();
        }

        $seed = crc32($subscription->uuid.':'.$priority.':'.now()->format('Y-m-d'));
        $target = $seed % $totalWeight;
        $cursor = 0;

        foreach ($gateways as $gateway) {
            $cursor += max(1, (int) $gateway->weight);

            if ($target < $cursor) {
                return $gateway;
            }
        }

        return $gateways->last();
    }

    public function providerForGateway(PaymentGatewayConfig $gateway): PaymentProvider
    {
        return PaymentProvider::from($gateway->provider);
    }
}
