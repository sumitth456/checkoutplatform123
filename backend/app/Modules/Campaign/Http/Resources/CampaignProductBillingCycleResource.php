<?php

namespace App\Modules\Campaign\Http\Resources;

use App\Models\CampaignProductBillingCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CampaignProductBillingCycle */
class CampaignProductBillingCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_product_id' => $this->campaign_product_id,
            'cycle_number' => $this->cycle_number,
            'label' => $this->label(),
            'product_id' => $this->product_id,
            'bill_product_uuid' => $this->whenLoaded('product', fn () => $this->product?->uuid),
            'bill_product_sku' => $this->whenLoaded('product', fn () => $this->product?->sku),
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
