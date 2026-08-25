<?php

namespace App\Modules\Campaign\Http\Resources;

use App\Models\CampaignFunnelMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CampaignFunnelMapping */
class CampaignFunnelMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'campaign' => $this->whenLoaded('campaign', fn () => [
                'uuid' => $this->campaign->uuid,
                'public_id' => $this->campaign->public_id,
                'name' => $this->campaign->name,
            ]),
            'funnel' => $this->whenLoaded('funnel', fn () => [
                'uuid' => $this->funnel->uuid,
                'name' => $this->funnel->name,
                'slug' => $this->funnel->slug,
            ]),
            'landing_url' => $this->landing_url,
            'api_key_preview' => $this->api_key_preview,
            'status' => $this->status->value,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
