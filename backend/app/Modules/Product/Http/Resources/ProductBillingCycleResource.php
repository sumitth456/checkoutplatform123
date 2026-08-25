<?php

namespace App\Modules\Product\Http\Resources;

use App\Models\ProductBillingCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductBillingCycle */
class ProductBillingCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cycle_number' => $this->cycle_number,
            'label' => $this->label(),
            'bill_product_id' => $this->bill_product_id,
            'bill_product_uuid' => $this->whenLoaded('billProduct', fn () => $this->billProduct?->uuid),
            'bill_product_sku' => $this->whenLoaded('billProduct', fn () => $this->billProduct?->sku),
            'variant_id' => $this->variant_id,
            'variant_sku' => $this->whenLoaded('variant', fn () => $this->variant?->sku),
            'price' => $this->price,
            'shipping_price' => $this->shipping_price,
            'bill_delay_days' => $this->bill_delay_days,
            'delay_fulfillment' => $this->delay_fulfillment,
            'shippable' => $this->shippable,
            'warehouse' => $this->warehouse,
            'subscription_enabled' => $this->subscription_enabled,
            'repeat_forever' => $this->repeat_forever,
            'inventory_rule' => $this->inventory_rule?->value,
            'inventory_quantity' => $this->inventory_quantity,
            'coupon_override' => $this->coupon_override,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
