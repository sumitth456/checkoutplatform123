<?php

namespace App\Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFunnelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email'],
            'customer.first_name' => ['nullable', 'string', 'max:255'],
            'customer.last_name' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.variant_uuid' => ['nullable', 'uuid', 'exists:variants,uuid'],
            'items.*.variant_sku' => ['nullable', 'string', 'max:255'],
            'items.*.product_uuid' => ['nullable', 'uuid', 'exists:products,uuid'],
            'items.*.product_sku' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.color' => ['nullable', 'string', 'max:100'],
            'items.*.order_bump_uuid' => ['nullable', 'uuid'],
            'items.*.campaign_product_line_id' => ['nullable', 'integer'],
            'metadata.campaign_uuid' => ['nullable', 'uuid', 'exists:campaigns,uuid'],
            'metadata.landing_page_uuid' => ['nullable', 'uuid', 'exists:landing_pages,uuid'],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'billing_address' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'subscription_plan_uuid' => ['nullable', 'uuid', 'exists:subscription_plans,uuid'],
            'fulfillment_delay_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'funnel_uuid' => ['nullable', 'uuid', 'exists:funnels,uuid'],
            'funnel_slug' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
