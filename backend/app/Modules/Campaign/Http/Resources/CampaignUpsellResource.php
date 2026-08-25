<?php

namespace App\Modules\Campaign\Http\Resources;

use App\Models\CampaignUpsell;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CampaignUpsell */
class CampaignUpsellResource extends JsonResource
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
            'trigger' => $this->trigger->value,
            'trigger_config' => $this->trigger_config,
            'position' => $this->position->value,
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'is_active' => $this->is_active,
        ];
    }
}
