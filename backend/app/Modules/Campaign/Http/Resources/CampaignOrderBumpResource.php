<?php

namespace App\Modules\Campaign\Http\Resources;

use App\Models\CampaignOrderBump;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CampaignOrderBump */
class CampaignOrderBumpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'uuid' => $this->product->uuid,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ]),
            'priority' => $this->priority,
            'sort_order' => $this->sort_order,
            'bump_price' => $this->bump_price,
            'position' => $this->position->value,
            'display_rule' => $this->display_rule->value,
            'display_rule_config' => $this->display_rule_config,
            'is_active' => $this->is_active,
        ];
    }
}
