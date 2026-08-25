<?php

namespace App\Modules\Product\Http\Resources;

use App\Modules\Variant\Http\Resources\VariantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'sku' => $this->sku,
            'currency' => $this->currency,
            'base_price' => $this->base_price,
            'compare_at_price' => $this->compare_at_price,
            'is_taxable' => $this->is_taxable,
            'requires_shipping' => $this->requires_shipping,
            'is_subscription' => $this->is_subscription,
            'billing_interval' => $this->billing_interval,
            'billing_interval_count' => $this->billing_interval_count,
            'trial_days' => $this->trial_days,
            'images' => $this->images,
            'metadata' => $this->metadata,
            'published_at' => $this->published_at,
            'variants_count' => $this->whenCounted('variants'),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->whenNotNull($this->deleted_at),
        ];
    }
}
