<?php

namespace App\Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'payment_method_type' => $this->payment_method_type,
            'fulfillment_status' => $this->fulfillment_status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'coupon_code' => $this->coupon_code,
            'refunded_total' => $this->refunded_total,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'notes' => $this->notes,
            'funnel_id' => $this->funnel_id,
            'campaign_id' => $this->campaign_id,
            'country_code' => $this->country_code,
            'device_type' => $this->device_type,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'landing_page_url' => $this->landing_page_url,
            'landing_page_id' => $this->landing_page_id,
            'referrer_url' => $this->referrer_url,
            'utm_parameters' => $this->utm_parameters,
            'landing_page_data' => $this->landing_page_data,
            'metadata' => $this->metadata,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'uuid' => $this->customer->uuid,
                'email' => $this->customer->email,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
            ]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'fulfillments' => FulfillmentResource::collection($this->whenLoaded('fulfillments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
