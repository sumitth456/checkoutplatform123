<?php

namespace App\Modules\Product\BillingCycles;

use Illuminate\Validation\Rule;

final class BillingCycleRequestRules
{
    /**
     * @return array<string, mixed>
     */
    public static function cycleRules(bool $requireCycleNumber = false): array
    {
        $rules = [
            'bill_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:variants,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'shipping_price' => ['nullable', 'numeric', 'min:0'],
            'bill_delay_days' => ['nullable', 'integer', 'min:0'],
            'delay_fulfillment' => ['nullable', 'boolean'],
            'shippable' => ['nullable', 'boolean'],
            'warehouse' => ['nullable', 'string', 'max:120'],
            'subscription_enabled' => ['nullable', 'boolean'],
            'repeat_forever' => ['nullable', 'boolean'],
            'inventory_rule' => ['nullable', Rule::in(['inherit', 'enforce_limits', 'ignore_inventory', 'block_oversell'])],
            'inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'coupon_override' => ['nullable', 'array'],
            'coupon_override.enabled' => ['nullable', 'boolean'],
            'coupon_override.coupon_code' => ['nullable', 'string', 'max:64'],
            'coupon_override.discount_type' => ['nullable', 'string', 'in:fixed,percent'],
            'coupon_override.discount_value' => ['nullable', 'numeric', 'min:0'],
        ];

        $rules['cycle_number'] = $requireCycleNumber
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public static function syncRules(): array
    {
        return [
            'cycles' => ['required', 'array', 'min:1'],
            'cycles.*.cycle_number' => ['required', 'integer', 'min:1'],
            'cycles.*.bill_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'cycles.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'cycles.*.variant_id' => ['nullable', 'integer', 'exists:variants,id'],
            'cycles.*.price' => ['nullable', 'numeric', 'min:0'],
            'cycles.*.shipping_price' => ['nullable', 'numeric', 'min:0'],
            'cycles.*.bill_delay_days' => ['nullable', 'integer', 'min:0'],
            'cycles.*.delay_fulfillment' => ['nullable', 'boolean'],
            'cycles.*.shippable' => ['nullable', 'boolean'],
            'cycles.*.warehouse' => ['nullable', 'string', 'max:120'],
            'cycles.*.subscription_enabled' => ['nullable', 'boolean'],
            'cycles.*.repeat_forever' => ['nullable', 'boolean'],
            'cycles.*.inventory_rule' => ['nullable', Rule::in(['inherit', 'enforce_limits', 'ignore_inventory', 'block_oversell'])],
            'cycles.*.inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'cycles.*.coupon_override' => ['nullable', 'array'],
        ];
    }
}
