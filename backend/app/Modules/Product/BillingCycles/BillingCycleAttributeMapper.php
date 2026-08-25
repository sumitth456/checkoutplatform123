<?php

namespace App\Modules\Product\BillingCycles;

use App\Models\CampaignProductBillingCycle;
use App\Models\ProductBillingCycle;

final class BillingCycleAttributeMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function fromProductCycle(ProductBillingCycle $cycle): array
    {
        return [
            'cycle_number' => $cycle->cycle_number,
            'product_id' => $cycle->bill_product_id,
            'variant_id' => $cycle->variant_id,
            'price' => $cycle->price,
            'shipping_price' => $cycle->shipping_price,
            'bill_delay_days' => $cycle->bill_delay_days,
            'delay_fulfillment' => $cycle->delay_fulfillment,
            'shippable' => $cycle->shippable,
            'warehouse' => $cycle->warehouse,
            'subscription_enabled' => $cycle->subscription_enabled,
            'repeat_forever' => $cycle->repeat_forever,
            'inventory_rule' => $cycle->inventory_rule?->value,
            'inventory_quantity' => $cycle->inventory_quantity,
            'coupon_override' => $cycle->coupon_override,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromCampaignCycle(CampaignProductBillingCycle $cycle): array
    {
        return [
            'cycle_number' => $cycle->cycle_number,
            'product_id' => $cycle->product_id,
            'variant_id' => $cycle->variant_id,
            'price' => $cycle->price,
            'shipping_price' => $cycle->shipping_price,
            'bill_delay_days' => $cycle->bill_delay_days,
            'delay_fulfillment' => $cycle->delay_fulfillment,
            'shippable' => $cycle->shippable,
            'warehouse' => $cycle->warehouse,
            'subscription_enabled' => $cycle->subscription_enabled,
            'repeat_forever' => $cycle->repeat_forever,
            'inventory_rule' => $cycle->inventory_rule?->value,
            'inventory_quantity' => $cycle->inventory_quantity,
            'coupon_override' => $cycle->coupon_override,
        ];
    }

    public static function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>|null
     */
    public static function couponOverride(array $attributes, ?array $existing): ?array
    {
        if (array_key_exists('coupon_override', $attributes)) {
            $override = $attributes['coupon_override'];

            if (! is_array($override) || ! ($override['enabled'] ?? false)) {
                return null;
            }

            return [
                'enabled' => true,
                'coupon_code' => filled($override['coupon_code'] ?? null) ? (string) $override['coupon_code'] : null,
                'discount_type' => filled($override['discount_type'] ?? null) ? (string) $override['discount_type'] : null,
                'discount_value' => isset($override['discount_value']) ? (float) $override['discount_value'] : null,
            ];
        }

        return $existing;
    }
}
