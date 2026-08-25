<?php

namespace App\Modules\Fulfillment\Services;

use App\Models\FulfillmentHouse;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\ShippingRule;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;

class FulfillmentAssignmentEngine
{
    /**
     * @return array{warehouse: ?Warehouse, fulfillment_house: ?FulfillmentHouse, shipping_carrier: ?ShippingCarrier, shipping_rule: ?ShippingRule, shipping_method: ?string}
     */
    public function assignForOrder(Order $order): array
    {
        $country = strtoupper((string) ($order->shipping_country ?? $order->billing_country ?? 'US'));
        $state = strtoupper((string) ($order->shipping_state ?? ''));
        $orderValue = (float) $order->grand_total;
        $isSubscription = filled($order->source_subscription_id) || filled($order->metadata['subscription_plan_uuid'] ?? null);

        $rules = ShippingRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $matched = $this->matchRule($rules, $country, $state, $orderValue, $isSubscription, $order->platform_campaign_id);

        $warehouse = $matched?->warehouse
            ?? $this->resolveWarehouseByInventory($order, $country);

        $fulfillmentHouse = $matched?->fulfillmentHouse
            ?? $warehouse?->fulfillmentHouse
            ?? FulfillmentHouse::query()->where('is_active', true)->orderBy('priority')->first();

        $carrier = $matched?->shippingCarrier
            ?? ShippingCarrier::query()->where('is_active', true)->first();

        return [
            'warehouse' => $warehouse,
            'fulfillment_house' => $fulfillmentHouse,
            'shipping_carrier' => $carrier,
            'shipping_rule' => $matched,
            'shipping_method' => $matched?->shipping_method ?? 'standard',
        ];
    }

    /** @param Collection<int, ShippingRule> $rules */
    private function matchRule(
        Collection $rules,
        string $country,
        string $state,
        float $orderValue,
        bool $isSubscription,
        ?int $campaignId,
    ): ?ShippingRule {
        foreach ($rules as $rule) {
            if ($rule->country_code && strtoupper($rule->country_code) !== $country) {
                continue;
            }

            if ($rule->state_code && strtoupper($rule->state_code) !== $state) {
                continue;
            }

            if ($rule->min_order_value !== null && $orderValue < (float) $rule->min_order_value) {
                continue;
            }

            if ($rule->max_order_value !== null && $orderValue > (float) $rule->max_order_value) {
                continue;
            }

            if ($rule->subscription_only && ! $isSubscription) {
                continue;
            }

            if ($rule->platform_campaign_id && $rule->platform_campaign_id !== $campaignId) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    private function resolveWarehouseByInventory(Order $order, string $country): ?Warehouse
    {
        $variantIds = $order->items()->pluck('variant_id')->filter()->all();

        if ($variantIds === []) {
            return Warehouse::query()
                ->where('is_active', true)
                ->where('country_code', $country)
                ->orderBy('priority')
                ->first()
                ?? Warehouse::query()->where('is_active', true)->orderBy('priority')->first();
        }

        return Warehouse::query()
            ->where('is_active', true)
            ->whereHas('inventory', function ($query) use ($variantIds): void {
                $query->whereIn('variant_id', $variantIds)->where('available', '>', 0);
            })
            ->orderBy('priority')
            ->first()
            ?? Warehouse::query()->where('is_active', true)->orderBy('priority')->first();
    }

    public function reserveInventory(Warehouse $warehouse, int $variantId, int $quantity): void
    {
        $inventory = WarehouseInventory::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'variant_id' => $variantId],
            ['available' => 0, 'reserved' => 0, 'committed' => 0],
        );

        $available = max(0, (int) $inventory->available - $quantity);
        $inventory->update([
            'available' => $available,
            'reserved' => (int) $inventory->reserved + $quantity,
        ]);
    }
}
