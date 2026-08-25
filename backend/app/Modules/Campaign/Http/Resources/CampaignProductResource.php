<?php

namespace App\Modules\Campaign\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class CampaignProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->pivot;

        return [
            'product_id' => $this->id,
            'product_uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'priority' => $pivot->priority ?? null,
            'sort_order' => $pivot->sort_order ?? null,
            'campaign_price' => $pivot->campaign_price ?? null,
            'min_quantity' => $pivot->min_quantity ?? null,
            'max_quantity' => $pivot->max_quantity ?? null,
            'inventory_rule' => $pivot->inventory_rule instanceof \BackedEnum
                ? $pivot->inventory_rule->value
                : $pivot->inventory_rule,
            'variant_settings' => $pivot->variant_settings ?? [],
            'is_active' => (bool) ($pivot->is_active ?? true),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'uuid' => $variant->uuid,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => $variant->price,
                'inventory_quantity' => $variant->inventory_quantity,
            ])),
        ];
    }
}
